<?php

/**
 * Copyright (C) 2016-18 Benjamin Heisig
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Benjamin Heisig <https://benjamin.heisig.name/>
 * @copyright Copyright (C) 2016-17 Benjamin Heisig
 * @license http://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License (AGPL)
 * @link https://github.com/bheisig/i-doit-cli
 */

declare(strict_types=1);

namespace bheisig\idoitcli\Command;

/**
 * Command "rack"
 */
class Rack extends Command {

    protected $objectID = 0;
    protected $objectTitle = '';
    protected $objectTypeTitle = '';
    protected $objectTypeConstant = '';
    protected $locationPath = '';
    protected $rackUnits = 0;
    protected $hosts = [];

    protected $innerWidth = 0;
    protected $paddingLeft = 1;
    protected $paddingRight = 1;
    protected $digits = 2;
    protected $maxWidth = 80;
    protected $marginLeft = 4;

    /**
     * Execute command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        $this->log->info($this->getDescription());

        $this->log->debug('Collect data…');

        switch (count($this->config['arguments'])) {
            case 0:
                break;
            case 1:
                $this->identifyObject($this->config['arguments'][0]);
                break;
            default:
                throw new \BadMethodCallException(
                    'Too many arguments; please provide only one object title or numeric identifier'
                );
        }

        $this->innerWidth =
            $this->maxWidth -
            $this->marginLeft -
            // We have digits on both sides:
            $this->digits * 2 -
            $this->paddingLeft -
            $this->paddingRight -
            // Amount of vertical lines in rack:
            4;

        $this
            ->loadRack($this->objectID)
            ->printHeader()
            ->printRackHeader()
            ->printRackBody()
            ->printRackFooter();

        return $this;
    }

    /**
     * @param string $candidate
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function identifyObject(string $candidate): self {
        if (is_numeric($candidate) && (int) $candidate > 0) {
            $object = $this->useIdoitAPI()->getCMDBObject()->read((int) $candidate);

            if (count($object) > 0) {
                $this->objectID = (int) $object['id'];
                $this->objectTitle = $object['title'];
            } else {
                throw new \BadMethodCallException(sprintf(
                    'Object not found by numeric identifier %s',
                    $candidate
                ));
            }
        } else {
            $objects = $this->useIdoitAPI()->fetchObjects([
                'title' => $candidate
            ]);

            switch (count($objects)) {
                case 0:
                    throw new \BadMethodCallException(sprintf(
                        'Object not found by title "%s"',
                        $candidate
                    ));
                case 1:
                    $object = end($objects);
                    $this->objectID = (int) $object['id'];
                    $this->objectTitle = $object['title'];
                    break;
                default:
                    throw new \RuntimeException(sprintf(
                        'Object title "%s" is ambiguous',
                        $candidate
                    ));
            }
        }

        return $this;
    }

    /**
     * @param int $objectID Object identifier
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function loadRack(int $objectID): self {
        try {
            $result = $this->useIdoitAPI()->getCMDBCategory()->batchRead(
                [$objectID],
                [
                    'C__CATG__LOCATION',
                    'C__CATG__FORMFACTOR',
                    'C__CATG__OBJECT'
                ]
            );

            if (count($result) !== 3) {
                throw new \RuntimeException('Unexpected result');
            }

            if (!array_key_exists(0, $result) ||
                !is_array($result[0]) ||
                !array_key_exists(0, $result[0]) ||
                !is_array($result[0][0])) {
                throw new \RuntimeException('Rack is not located anywhere');
            }

            $this->locationPath = $this->identifyLocationPath($result[0][0]);

            if (!array_key_exists(1, $result) ||
                !is_array($result[1]) ||
                !array_key_exists(0, $result[1]) ||
                !is_array($result[1][0])) {
                throw new \RuntimeException('Rack has no units');
            }

            $this->rackUnits = $this->identifyRackUnits($result[1][0]);

            if (!array_key_exists(2, $result) ||
                !is_array($result[2]) ||
                count($result[2]) === 0) {
                $this->log->notice(
                    'Rack has no hardware'
                );
            } else {
                $this->hosts = $this->loadHardware($result[2]);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Unable to load information about rack "%s" [%s]: %s',
                $this->objectTitle,
                $this->objectID,
                $e->getMessage()
            ));
        }

        return $this;
    }

    protected function identifyLocationPath(array $location): string {
        if (!array_key_exists('parent', $location) ||
            !is_array($location['parent']) ||
            !array_key_exists('location_path', $location['parent']) ||
            !is_string($location['parent']['location_path']) ||
            strlen($location['parent']['location_path']) === 0) {
            throw new \RuntimeException('Unknown location path');
        }

        return $location['parent']['location_path'];
    }

    protected function identifyRackUnits(array $formfactor): int {
        if (!array_key_exists('rackunits', $formfactor) ||
            !is_numeric($formfactor['rackunits']) ||
            (int) $formfactor['rackunits'] <= 0) {
            throw new \RuntimeException('Unknown rack units');
        }

        return (int) $formfactor['rackunits'];
    }

    /**
     * @param array $hosts
     *
     * @return array
     *
     * @throws \Exception on error^
     */
    protected function loadHardware(array $hosts): array {
        $objectIDs = [];

        foreach ($hosts as $host) {
            if (!array_key_exists('objID', $host) ||
                !$this->validate->isIDAsString($host['objID'])) {
                throw new \RuntimeException('Unknown host');
            }

            $objectIDs[] = (int) $host['objID'];
        }

        $objects = $this->useIdoitAPI()->getCMDBObjects()->read(
            ['ids' => $objectIDs],
            100,
            0,
            null,
            null,
            [
                'C__CATG__LOCATION',
                'C__CATG__FORMFACTOR'
            ]
        );

        return $objects;
    }

    protected function printHeader(): self {
        $this->log->printEmptyLine();

        $title = sprintf(
            '%s > <strong>%s</strong>',
            $this->locationPath,
            $this->objectTitle
        );

        // Minus style tag:
        $titleLength = strlen($title) - 17;

        $id = sprintf(
            '<dim>[%s]</dim>',
            $this->objectID
        );

        // Minus style tag:
        $idLength = strlen($id) - 11;

        $minDistance = 1;

        if ($titleLength + $minDistance + $idLength > $this->maxWidth) {
            $this->log->info($title);
            $this->log->info($id);
        } else {
            $distance = $this->maxWidth - ($titleLength + $idLength);

            $this->log->info(
                '%s%s%s',
                $title,
                str_repeat(' ', $distance),
                $id
            );
        }

        $this->log->printEmptyLine();

        return $this;
    }

    protected function printRackHeader(): self {
        $this->log->info(
            '%s<dim>╔%s╦%s╦%s╗</dim>',
            str_repeat(' ', $this->marginLeft),
            str_repeat('═', $this->digits),
            str_repeat('═', $this->innerWidth + $this->paddingLeft + $this->paddingRight),
            str_repeat('═', $this->digits)
        );

        return $this;
    }

    protected function printRackBody(): self {
        for ($i = $this->rackUnits; $i > 0; $i--) {
            $this->log->info(
                '%s<dim>╠%s╬%s╬%s╣</dim>',
                str_repeat(' ', $this->marginLeft),
                str_repeat('═', $this->digits),
                str_repeat('═', $this->innerWidth + $this->paddingLeft + $this->paddingRight),
                str_repeat('═', $this->digits)
            );

            $digit = '' . $i;

            if ($i < 10) {
                $digit = '0' . $i;
            }

            $this->log->info(
                '%s<dim>║%s║%s║%s║</dim>',
                str_repeat(' ', $this->marginLeft),
                $digit,
                str_repeat(' ', $this->innerWidth + $this->paddingLeft + $this->paddingRight),
                $digit
            );
        }

        $this->log->info(
            '%s<dim>╠%s╬%s╬%s╣</dim>',
            str_repeat(' ', $this->marginLeft),
            str_repeat('═', $this->digits),
            str_repeat('═', $this->innerWidth + $this->paddingLeft + $this->paddingRight),
            str_repeat('═', $this->digits)
        );

        return $this;
    }

    protected function printRackFooter(): self {
        $this->log->info(
            '%s<dim>╚%s╩%s╩%s╝</dim>',
            str_repeat(' ', $this->marginLeft),
            str_repeat('═', $this->digits),
            str_repeat('═', $this->innerWidth + $this->paddingLeft + $this->paddingRight),
            str_repeat('═', $this->digits)
        );

        return $this;
    }

    /**
     * Print usage of command
     *
     * @return self Returns itself
     */
    public function printUsage(): self {
        $this->log->info(
            <<< EOF
%3\$s

<strong>USAGE</strong>
    \$ %1\$s %2\$s [OBJECT]
    
<strong>ARGUMENTS</strong>
    OBJECT              <dim>Object title or numeric identifier</dim>

<strong>COMMON OPTIONS</strong>
    -c <u>FILE</u>,            <dim>Include settings stored in a JSON-formatted</dim>
    --config=<u>FILE</u>       <dim>configuration file FILE; repeat option for more</dim>
                        <dim>than one FILE</dim>
    -s <u>KEY=VALUE</u>,       <dim>Add runtime setting KEY with its VALUE; separate</dim>
    --setting=<u>KEY=VALUE</u> <dim>nested keys with ".", for example "key1.key2=123";</dim>
                        <dim>repeat option for more than one KEY</dim>

    --no-colors         <dim>Do not print colored messages</dim>
    -q, --quiet         <dim>Do not output messages, only errors</dim>
    -v, --verbose       <dim>Be more verbose</dim>

    -h, --help          <dim>Print this help or information about a</dim>
                        <dim>specific command</dim>
    --version           <dim>Print version information</dim>

    -y, --yes           <dim>No user interaction required; answer questions</dim>
                        <dim>automatically with default values</dim>

<strong>EXAMPLES</strong>
    <dim># Select rack by its title:</dim>
    \$ %1\$s %2\$s "Colocation Rack A001"
    
    <dim># …or by its numeric identifier:</dim>
    \$ %1\$s %2\$s 123
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
