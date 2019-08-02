<?php

/**
 * Copyright (C) 2016-19 Benjamin Heisig
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

use \Exception;
use \BadMethodCallException;
use \RuntimeException;

/**
 * Command "rack"
 */
class Rack extends Command {

    public const OPTION_FRONT = 'front';
    public const OPTION_BACK = 'back';
    public const OPTION_SKIP_EMPTY_UNITS = 'skip-empty-units';

    protected const HORIZONTAL_ASSEMBLED = 3;
    protected const FRONT_SIDE = 1;
    protected const BACK_SIDE = 0;
    protected const BOTH_SIDES = 2;
    protected const EMPTY_CHAR = '█';
    protected const ELLIPSIS_CHAR = '…';

    protected $objectID = 0;
    protected $objectTitle = '';
    protected $objectTypeTitle = '';
    protected $objectTypeConstant = '';
    protected $locationPath = '';
    protected $rackUnits = 0;
    protected $formattedUnits = [];
    protected $side = self::FRONT_SIDE;

    protected $innerWidth = 0;
    protected $paddingLeft = 1;
    protected $paddingRight = 1;
    protected $digits = 2;
    protected $minWidth = 30;
    protected $maxWidth = 80;
    protected $marginLeft = 4;

    /**
     * Execute command
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    public function execute(): self {
        $this->log
            ->printAsMessage()
            ->info($this->getDescription())
            ->printEmptyLine()
            ->debug('Collect data…');

        $this
            ->identifyRackObject()
            ->selectSide()
            ->setDimensions()
            ->loadRack($this->objectID)
            ->printHeader()
            ->printRackHeader()
            ->printRackBody($this->formattedUnits)
            ->printRackFooter();

        return $this;
    }

    /**
     * @return self Returns itself
     * @throws Exception on error
     */
    protected function identifyRackObject(): self {
        switch (count($this->config['arguments'])) {
            case 0:
                if ($this->useUserInteraction()->isInteractive() === false) {
                    throw new BadMethodCallException(
                        'No object, no visuals'
                    );
                }

                $object = $this->askForObject();
                $this->objectID = (int) $object['id'];
                $this->objectTitle = $object['title'];
                break;
            case 1:
                $object = $this->useIdoitAPI()->identifyObject(
                    $this->config['arguments'][0]
                );

                $this->objectID = (int) $object['id'];
                $this->objectTitle = $object['title'];
                break;
            default:
                throw new BadMethodCallException(
                    'Too many arguments; please provide only one object title or numeric identifier'
                );
        }

        return $this;
    }

    protected function selectSide(): self {
        if (!array_key_exists('options', $this->config) ||
            !is_array($this->config['options'])) {
            return $this;
        }

        if (array_key_exists(self::OPTION_FRONT, $this->config['options']) &&
            $this->config['options'][self::OPTION_FRONT] === true) {
            $this->side = self::FRONT_SIDE;
        } elseif (array_key_exists(self::OPTION_BACK, $this->config['options']) &&
            $this->config['options'][self::OPTION_BACK] === true) {
            $this->side = self::BACK_SIDE;
        }

        return $this;
    }

    protected function skipEmptyRackUnits(): bool {
        if (array_key_exists('options', $this->config) &&
            is_array($this->config['options']) &&
            array_key_exists(self::OPTION_SKIP_EMPTY_UNITS, $this->config['options']) &&
            $this->config['options'][self::OPTION_SKIP_EMPTY_UNITS] === true) {
            return true;
        }

        return false;
    }

    /**
     * @return self
     * @throws Exception on error
     */
    protected function setDimensions(): self {
        $this->innerWidth =
            $this->maxWidth -
            $this->marginLeft -
            // We have digits on both sides:
            $this->digits * 2 -
            $this->paddingLeft -
            $this->paddingRight -
            // Amount of vertical lines in rack:
            4;

        if ($this->innerWidth <= 0 ||
            $this->maxWidth < $this->minWidth) {
            throw new Exception('There is too few space left for each rack unit.');
        }

        return $this;
    }

    /**
     * @param int $objectID Object identifier
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function loadRack(int $objectID): self {
        try {
            $categoryConstants = [
                'C__CATG__LOCATION',
                'C__CATG__FORMFACTOR',
                'C__CATG__OBJECT'
            ];

            $result = $this->useIdoitAPIFactory()->getCMDBCategory()->batchRead(
                [$objectID],
                $categoryConstants
            );

            if (count($result) !== count($categoryConstants)) {
                throw new RuntimeException('Unexpected result');
            }

            if (!array_key_exists(0, $result) ||
                !is_array($result[0]) ||
                !array_key_exists(0, $result[0]) ||
                !is_array($result[0][0])) {
                throw new RuntimeException('Rack is not located anywhere');
            }

            $this->locationPath = $this->identifyLocationPath($result[0][0]);

            if (!array_key_exists(1, $result) ||
                !is_array($result[1]) ||
                !array_key_exists(0, $result[1]) ||
                !is_array($result[1][0])) {
                throw new RuntimeException('Rack has no units');
            }

            $this->rackUnits = $this->identifyRackUnits($result[1][0]);

            if (!array_key_exists(2, $result) ||
                !is_array($result[2]) ||
                count($result[2]) === 0) {
                $this->log->notice(
                    'Rack has no hardware'
                );
            } else {
                $this->formattedUnits =
                    $this->formatUnits(
                        $this->sortHardware(
                            $this->reduceHardware(
                                $this->mapHardware(
                                    $this->loadHardware($result[2])
                                )
                            )
                        )
                    );
            }
        } catch (Exception $e) {
            throw new RuntimeException(sprintf(
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
            throw new RuntimeException('Unknown location path');
        }

        return $location['parent']['location_path'];
    }

    protected function identifyRackUnits(array $formfactor): int {
        if (!array_key_exists('rackunits', $formfactor) ||
            !is_numeric($formfactor['rackunits']) ||
            (int) $formfactor['rackunits'] <= 0) {
            throw new RuntimeException('Unknown rack units');
        }

        return (int) $formfactor['rackunits'];
    }

    /**
     * @param array $hosts
     *
     * @return array
     *
     * @throws Exception on error^
     */
    protected function loadHardware(array $hosts): array {
        $objectIDs = [];

        foreach ($hosts as $host) {
            if (!array_key_exists('objID', $host) ||
                !$this->useValidate()->isIDAsString($host['objID'])) {
                throw new RuntimeException('Unknown host');
            }

            $objectIDs[] = (int) $host['objID'];
        }

        $objects = $this->useIdoitAPIFactory()->getCMDBObjects()->read(
            ['ids' => $objectIDs],
            null,
            null,
            null,
            null,
            [
                'C__CATG__LOCATION',
                'C__CATG__FORMFACTOR'
            ]
        );

        return $objects;
    }

    protected function mapHardware(array $hosts): array {
        $filteredHosts = [];

        foreach ($hosts as $host) {
            if (!array_key_exists('categories', $host) ||
                !is_array($host['categories'])) {
                continue;
            }

            if (!array_key_exists('C__CATG__LOCATION', $host['categories']) ||
                !is_array($host['categories']['C__CATG__LOCATION']) ||
                !array_key_exists(0, $host['categories']['C__CATG__LOCATION']) ||
                !is_array($host['categories']['C__CATG__LOCATION'][0])) {
                continue;
            }

            if (!array_key_exists('option', $host['categories']['C__CATG__LOCATION'][0]) ||
                !is_array($host['categories']['C__CATG__LOCATION'][0]['option']) ||
                !array_key_exists('title', $host['categories']['C__CATG__LOCATION'][0]['option']) ||
                (int) $host['categories']['C__CATG__LOCATION'][0]['option']['id'] !== self::HORIZONTAL_ASSEMBLED) {
                continue;
            }

            if (!array_key_exists('insertion', $host['categories']['C__CATG__LOCATION'][0]) ||
                !is_array($host['categories']['C__CATG__LOCATION'][0]['insertion']) ||
                !array_key_exists('id', $host['categories']['C__CATG__LOCATION'][0]['insertion']) ||
                !in_array(
                    (int) $host['categories']['C__CATG__LOCATION'][0]['insertion']['id'],
                    [$this->side, self::BOTH_SIDES]
                )
            ) {
                continue;
            }

            if (!array_key_exists('pos', $host['categories']['C__CATG__LOCATION'][0]) ||
                !is_array($host['categories']['C__CATG__LOCATION'][0]['pos']) ||
                !array_key_exists('title', $host['categories']['C__CATG__LOCATION'][0]['pos']) ||
                !is_numeric($host['categories']['C__CATG__LOCATION'][0]['pos']['title']) ||
                (int) $host['categories']['C__CATG__LOCATION'][0]['pos']['title'] <= 0) {
                continue;
            }

            if (!array_key_exists('C__CATG__FORMFACTOR', $host['categories']) ||
                !is_array($host['categories']['C__CATG__FORMFACTOR']) ||
                !array_key_exists(0, $host['categories']['C__CATG__FORMFACTOR']) ||
                !is_array($host['categories']['C__CATG__FORMFACTOR'][0])) {
                continue;
            }

            if (!array_key_exists('rackunits', $host['categories']['C__CATG__FORMFACTOR'][0]) ||
                !is_numeric($host['categories']['C__CATG__FORMFACTOR'][0]['rackunits']) ||
                (int) $host['categories']['C__CATG__FORMFACTOR'][0]['rackunits'] <= 0) {
                continue;
            }

            $filteredHosts[] = $host;
        }

        return $filteredHosts;
    }

    protected function reduceHardware(array $hosts): array {
        $reducedHosts = [];

        foreach ($hosts as $host) {
            $reducedHosts[] = [
                'id' => $host['id'],
                'title' => $host['title'],
                'type' => $host['type_title'],
                'lowestPosition' => $this->calculateLowestPosition(
                    (int) $host['categories']['C__CATG__LOCATION'][0]['pos']['title'],
                    (int) $host['categories']['C__CATG__FORMFACTOR'][0]['rackunits'],
                    $this->rackUnits
                ),
                'highestPosition' => $this->calculateHighestPosistion(
                    (int) $host['categories']['C__CATG__LOCATION'][0]['pos']['title'],
                    $this->rackUnits
                )
            ];
        }

        return $reducedHosts;
    }

    protected function sortHardware(array $hosts): array {
        $positions = [];

        foreach ($hosts as $host) {
            for ($position = $host['lowestPosition']; $position <= $host['highestPosition']; $position++) {
                $positions[$position] = $host;
            }
        }

        return $positions;
    }

    protected function drawObjectType(string $title): string {
        return sprintf(
            '[%s]',
            strtolower($title)
        );
    }

    protected function drawObjectID(int $objectID): string {
        return sprintf('[#%s]', $objectID);
    }

    protected function drawObjectTitle(string $objectTitle): string {
        return $objectTitle;
    }

    protected function calculateLowestPosition(int $position, int $usedRackUnits, int $allowedRackUnits): int {
        return $allowedRackUnits - $position + 2 - $usedRackUnits;
    }

    protected function calculateHighestPosistion(int $position, int $allowedRackUnits): int {
        return $allowedRackUnits - $position + 1;
    }

    protected function formatUnits(array $positionedHardware): array {
        $formattedUnits = [];

        for ($rackUnit = 1; $rackUnit <= $this->rackUnits; $rackUnit++) {
            $isOccupied = false;

            foreach ($positionedHardware as $occupiedRackUnit => $hardware) {
                if ($rackUnit === $occupiedRackUnit) {
                    $formattedUnits[$rackUnit] = $this->drawUnit($hardware);
                    $isOccupied = true;
                    break;
                }
            }

            if ($isOccupied === false) {
                $formattedUnits[$rackUnit] = $this->drawEmptyUnit();
            }
        }

        return $formattedUnits;
    }

    protected function drawUnit(array $hardware, $tryShorter = 0): string {
        switch ($tryShorter) {
            case 0:
                $title = $this->drawObjectTitle($hardware['title']);
                $type = $this->drawObjectType($hardware['type']);
                $identifier = $this->drawObjectID($hardware['id']);

                $emptySpace = (int) $this->innerWidth -
                    strlen($title) -
                    strlen($type) -
                    strlen($identifier) -
                    // Space between type and identifier:
                    1;

                if ($emptySpace <= 0) {
                    return $this->drawUnit($hardware, ($tryShorter+1));
                }

                return sprintf(
                    '%s<strong>%s</strong>%s%s %s%s',
                    str_repeat(' ', $this->paddingLeft),
                    $title,
                    str_repeat(' ', $emptySpace),
                    $type,
                    $identifier,
                    str_repeat(' ', $this->paddingRight)
                );
                break;
            case 1:
                $title = $this->drawObjectTitle($hardware['title']);
                $type = $this->drawObjectType($hardware['type']);

                $emptySpace = (int) $this->innerWidth -
                    strlen($title) -
                    strlen($type);

                if ($emptySpace <= 0) {
                    return $this->drawUnit($hardware, ($tryShorter+1));
                }

                return sprintf(
                    '%s<strong>%s</strong>%s%s%s',
                    str_repeat(' ', $this->paddingLeft),
                    $title,
                    str_repeat(' ', $emptySpace),
                    $type,
                    str_repeat(' ', $this->paddingRight)
                );
                break;
            case 2:
                $title = $this->drawObjectTitle($hardware['title']);

                $emptySpace = (int) $this->innerWidth -
                    strlen($title);

                if ($emptySpace <= 0) {
                    return $this->drawUnit($hardware, ($tryShorter+1));
                }

                return sprintf(
                    '%s<strong>%s</strong>%s%s',
                    str_repeat(' ', $this->paddingLeft),
                    $title,
                    str_repeat(' ', $emptySpace),
                    str_repeat(' ', $this->paddingRight)
                );
                break;
            default:
                $emptySpace = (int) $this->innerWidth - 1;

                if ($emptySpace <= 0) {
                    return '';
                }

                return sprintf(
                    '%s%s%s%s',
                    str_repeat(' ', $this->paddingLeft),
                    self::ELLIPSIS_CHAR,
                    str_repeat(' ', $emptySpace),
                    str_repeat(' ', $this->paddingRight)
                );
        }
    }

    protected function drawOccupiedUnit(): string {
        return sprintf(
            '%s%s%s',
            str_repeat(' ', $this->paddingLeft),
            str_repeat(' ', $this->innerWidth),
            str_repeat(' ', $this->paddingRight)
        );
    }

    protected function drawEmptyUnit() {
        return sprintf(
            '%s%s%s',
            str_repeat(' ', $this->paddingLeft),
            str_repeat(self::EMPTY_CHAR, $this->innerWidth),
            str_repeat(' ', $this->paddingRight)
        );
    }

    protected function drawDigit(int $number): string {
        $digit = '' . $number;

        if ($number < 10) {
            $digit = '0' . $number;
        }

        return $digit;
    }

    protected function printHeader(): self {
        $this->log
            ->printAsMessage()
            ->printEmptyLine();

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
            $this->log
                ->printAsOutput()
                ->info($title)
                ->info($id);
        } else {
            $distance = $this->maxWidth - ($titleLength + $idLength);

            $this->log
                ->printAsOutput()
                ->info(
                    '%s%s%s',
                    $title,
                    str_repeat(' ', $distance),
                    $id
                );
        }

        $this->log
            ->printAsMessage()
            ->printEmptyLine();

        return $this;
    }

    protected function printRackHeader(): self {
        $this->log
            ->printAsOutput()
            ->info(
                '%s<dim>╔%s╦%s╦%s╗</dim>',
                str_repeat(' ', $this->marginLeft),
                str_repeat('═', $this->digits),
                str_repeat('═', $this->innerWidth + $this->paddingLeft + $this->paddingRight),
                str_repeat('═', $this->digits)
            );

        return $this;
    }

    protected function printRackBody(array $formattedUnits): self {
        $lastUnit = '';

        for ($i = $this->rackUnits; $i > 0; $i--) {
            $content = $formattedUnits[$i];

            if ($this->skipEmptyRackUnits() &&
                strpos($content, self::EMPTY_CHAR, $this->paddingLeft) === 1) {
                continue;
            }

            $nonRelatedRU = true;

            if ($lastUnit === $content &&
                strpos($lastUnit, self::EMPTY_CHAR, $this->paddingLeft) !== 1) {
                $content = $this->drawOccupiedUnit();
                $nonRelatedRU = false;
            }

            if ($nonRelatedRU) {
                $this->log
                    ->printAsOutput()
                    ->info(
                        '%s<dim>╠%s╬%s╬%s╣</dim>',
                        str_repeat(' ', $this->marginLeft),
                        str_repeat('═', $this->digits),
                        str_repeat('═', $this->innerWidth + $this->paddingLeft + $this->paddingRight),
                        str_repeat('═', $this->digits)
                    );
            } else {
                $this->log
                    ->printAsOutput()
                    ->info(
                        '%s<dim>╠%s╣%s╠%s╣</dim>',
                        str_repeat(' ', $this->marginLeft),
                        str_repeat('═', $this->digits),
                        str_repeat(' ', $this->innerWidth + $this->paddingLeft + $this->paddingRight),
                        str_repeat('═', $this->digits)
                    );
            }

            $this->log
                ->printAsOutput()
                ->info(
                    '%s<dim>║%s║</dim>%s<dim>║%s║</dim>',
                    str_repeat(' ', $this->marginLeft),
                    $this->drawDigit($i),
                    $content,
                    $this->drawDigit($i)
                );

            $lastUnit = $formattedUnits[$i];
        }

        $this->log
            ->printAsOutput()
            ->info(
                '%s<dim>╠%s╬%s╬%s╣</dim>',
                str_repeat(' ', $this->marginLeft),
                str_repeat('═', $this->digits),
                str_repeat('═', $this->innerWidth + $this->paddingLeft + $this->paddingRight),
                str_repeat('═', $this->digits)
            );

        return $this;
    }

    protected function printRackFooter(): self {
        $this->log
            ->printAsOutput()
            ->info(
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
    \$ %1\$s %2\$s [OPTIONS] [OBJECT]
    
<strong>ARGUMENTS</strong>
    OBJECT              <dim>Object title or numeric identifier</dim>

<strong>COMMAND OPTIONS</strong>
    --%4\$s             <dim>Draw front side of rack (default)</dim>
    --%5\$s              <dim>Draw back side of rack</dim>
    --%6\$s  <dim>Draw only occupied rack units</dim>

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
    \$ %1\$s %2\$s Colocation\\ Rack\\ A001
    \$ %1\$s %2\$s "*A001"

    <dim># …or by its numeric identifier:</dim>
    \$ %1\$s %2\$s 123
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription(),
            self::OPTION_FRONT,
            self::OPTION_BACK,
            self::OPTION_SKIP_EMPTY_UNITS
        );

        return $this;
    }

}
