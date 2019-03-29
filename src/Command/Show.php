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

/**
 * Command "show"
 */
class Show extends Command {

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
            ->printEmptyLine();

        $object = $this->loadObject($this->config['arguments']);

        $this->printOutput($object);

        return $this;
    }

    /**
     * Load object based on CLI argument
     *
     * @param array $arguments CLI arguments
     *
     * @return array Everything about the object
     *
     * @throws Exception on error
     */
    protected function loadObject(array $arguments): array {
        switch (count($arguments)) {
            case 0:
                if ($this->useUserInteraction()->isInteractive() === false) {
                    throw new BadMethodCallException(
                        'No object, no output'
                    );
                }

                $object = $this->askForObject();

                $this->log->debug('Loading…');

                return $this
                    ->useIdoitAPIFactory()
                    ->getCMDBObject()
                    ->load((int) $object['id']);
            case 1:
                if (is_numeric($arguments[0])) {
                    $objectID = (int) $arguments[0];

                    if ($objectID <= 0) {
                        throw new Exception(
                            'Invalid object. Please specify an numeric identifier'
                        );
                    }

                    $this->log->debug('Loading…');

                    return $this
                        ->useIdoitAPIFactory()
                        ->getCMDBObject()
                        ->load($objectID);
                } else {
                    $objects = $this
                        ->useIdoitAPIFactory()
                        ->getCMDBObjects()
                        ->read(['title' => $arguments[0]]);

                    switch (count($objects)) {
                        case 0:
                            $this->log->warning('Object not found');
                            $object = $this->askForObject();

                            $this->log->debug('Loading…');

                            return $this
                                ->useIdoitAPIFactory()
                                ->getCMDBObject()
                                ->load((int) $object['id']);
                        case 1:
                            $objectID = (int) $objects[0]['id'];

                            $this->log->debug('Loading…');

                            return $this
                                ->useIdoitAPIFactory()
                                ->getCMDBObject()
                                ->load($objectID);
                        default:
                            $this->log
                                ->debug('Found %s objects', count($objects))
                                ->printEmptyLine();

                            foreach ($objects as $object) {
                                $this->log->info(
                                    '%s: %s',
                                    $object['id'],
                                    $object['title']
                                );
                            }

                            $this->log->printEmptyLine();

                            while (true) {
                                $answer = $this->useUserInteraction()->askQuestion(
                                    'Select object by title or numeric identifier:'
                                );

                                foreach ($objects as $object) {
                                    if (is_numeric($answer) &&
                                        (int) $answer >= 0 &&
                                        (int) $answer === (int) $object['id']) {
                                        $this->log->debug('Loading…');

                                        return $this
                                            ->useIdoitAPIFactory()
                                            ->getCMDBObject()
                                            ->load((int) $answer);
                                    } elseif ($answer === $object['title']) {
                                        $this->log->debug('Loading…');

                                        return $this
                                            ->useIdoitAPIFactory()
                                            ->getCMDBObject()
                                            ->load((int) $object['id']);
                                    }
                                }

                                $this->log->warning('Please try again.');
                            }
                            break;
                    }
                }
                break;
            default:
                throw new BadMethodCallException(
                    'Too many arguments; please provide only one object title or numeric identifier'
                );
        }

        return [];
    }

    /**
     * Print output
     *
     * @param array $object Everything about the object
     *
     * @throws Exception on error
     */
    protected function printOutput(array $object) {
        $this->log->printAsOutput()
            ->info('Title: <strong>%s</strong>', $object['title'])
            ->info('ID: <strong>%s</strong>', $object['id'])
            ->info('Type: <strong>%s</strong>', $object['type_title'])
            ->info('SYS-ID: <strong>%s</strong>', $object['sysid'])
            ->info('CMDB status: <strong>%s</strong>', $object['cmdb_status_title'])
            ->info('Created: <strong>%s</strong>', $object['created'])
            ->info('Updated: <strong>%s</strong>', $object['updated']);

        $categoryTypes = ['catg', 'cats', 'custom'];

        $blacklistedCategories = array_merge(
            $this
                ->useIdoitAPIFactory()
                ->getCMDBCategoryInfo()
                ->getVirtualCategoryConstants(),
            [
                'C__CATG__OVERVIEW',
                'C__CATG__RELATION',
                'C__CATG__LOGBOOK'
            ]
        );

        foreach ($categoryTypes as $categoryType) {
            if (!array_key_exists($categoryType, $object)) {
                continue;
            }

            foreach ($object[$categoryType] as $category) {
                if (in_array($category['const'], $blacklistedCategories)) {
                    continue;
                }

                $this->log
                    ->printAsMessage()
                    ->printEmptyLine()
                    ->info(
                        '<strong>%s</strong> [%s]',
                        $category['title'],
                        $category['const']
                    );

                switch (count($category['entries'])) {
                    case 0:
                        $this->log->printAsMessage()->info(
                            'No entries found'
                        );
                        continue 2;
                    case 1:
                        $this->log->printAsMessage()->info(
                            '1 entry found:'
                        );
                        break;
                    default:
                        $this->log->printAsMessage()->info(
                            '%s entries found:',
                            count($category['entries'])
                        );
                        break;
                }

                try {
                    $categoryInfo = $this->useCache()->getCategoryInfo($category['const']);
                } catch (Exception $e) {
                    $this->log->printAsMessage()->notice($e->getMessage());
                    continue;
                }

                foreach ($category['entries'] as $entry) {
                    $this->log->printAsMessage()->printEmptyLine();

                    foreach ($entry as $attribute => $value) {
                        if ($attribute === 'objID') {
                            continue;
                        }

                        if ($attribute === 'id') {
                            $this->log
                                ->printAsOutput()
                                ->debug('#%s', $value);
                            continue;
                        }

                        $this->handleAttribute()
                            ->load($categoryInfo['properties'][$attribute]);

                        if ($this->handleAttribute()->ignore()) {
                            continue;
                        }

                        $value = $this->handleAttribute()
                            ->encode($value);

                        if ($value === '') {
                            $value = '-';
                        }

                        $this->log->printAsOutput()->info(
                            '%s: <strong>%s</strong>',
                            $categoryInfo['properties'][$attribute]['title'],
                            $value
                        );
                    }
                }
            }
        }
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
    <dim># %3\$s by its title:</dim>
    \$ %1\$s %2\$s mylittleserver

    <dim># …or by its numeric identifier:</dim>
    \$ %1\$s %2\$s 42
    
    <dim>Handle spaces in title with care:</dim>
    \$ %1\$s %2\$s "My little server"
    \$ %1\$s %2\$s My\\ little\\ server

    <dim># If argument OBJECT is omitted you'll be asked for it:</dim>
    \$ %1\$s %2\$s
    Object? host01.example.com
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
