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

use bheisig\cli\IO;
use bheisig\idoitcli\Service\Cache;

/**
 * Command "show"
 */
class Show extends Command {

    use Cache;

    /**
     * Processes some routines before the execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function setup(): Command {
        parent::setup();

        if ($this->isCached() === false) {
            throw new \Exception(sprintf(
                'Unsufficient data. Please run "%s init" first.',
                $this->config['args'][0]
            ), 500);
        }

        return $this;
    }

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        $query = $this->getQuery();

        $objectID = 0;

        if ($query === '') {
            throw new \Exception('Empty query. Please specify an object title or identifier');
        } else if (is_numeric($query)) {
            $objectID = (int) $query;

            if ($objectID <= 0) {
                throw new \Exception('Invalid query. Please specify an object identifier');
            }
        } else {
            $objects = $this->useIdoitAPI()->getCMDBObjects()->read(['title' => $query]);

            switch (count($objects)) {
                case 0:
                    throw new \Exception('No object found.');
                    break;
                case 1:
                    $objectID = (int) $objects[0]['id'];
                    break;
                default:
                    IO::err('Found %s objects', count($objects));
                    IO::err('');

                    foreach ($objects as $object) {
                        IO::err(
                            '%s: %s',
                            $object['id'],
                            $object['title']
                        );
                    }

                    IO::err('');

                    while (true) {
                        $objectID = (int) IO::in('Which object do you like to show? [identifier]');

                        if ($objectID <= 0) {
                            IO::err('Please try again.');
                        } else {
                            break;
                        }
                    }
                    break;
            }
        }

        $object = $this->useIdoitAPI()->getCMDBObject()->load($objectID);

        IO::out('Title: %s', $object['title']);
        IO::out('ID: %s', $object['id']);
        IO::out('Type: %s', $object['type_title']);
        IO::out('SYS-ID: %s', $object['sysid']);
        IO::out('CMDB status: %s', $object['cmdb_status_title']);
        IO::out('Created: %s', $object['created']);
        IO::out('Updated: %s', $object['updated']);

        $categoryTypes = ['catg', 'cats'];

        foreach ($categoryTypes as $categoryType) {
            if (!array_key_exists($categoryType, $object)) {
                continue;
            }

            foreach ($object[$categoryType] as $category) {
                if (in_array($category['const'], ['C__CATG__RELATION', 'C__CATG__LOGBOOK'])) {
                    continue;
                }

                try {
                    $categoryInfo = $this->getCategoryInfo($category['const']);
                } catch (\Exception $e) {
                    IO::err($e->getMessage());
                    continue;
                }

                IO::err('');

                switch (count($category['entries'])) {
                    case 0;
                        IO::out(
                            'No entries in category "%s"',
                            $category['title']
                        );
                        continue 2;
                    case 1:
                        IO::out(
                            '1 entry in category "%s":',
                            $category['title']
                        );
                        break;
                    default:
                        IO::out(
                            '%s entries in category "%s":',
                            count($category['entries']),
                            $category['title']
                        );
                        break;
                }

                foreach ($category['entries'] as $entry) {
                    IO::err('');

                    foreach ($entry as $attribute => $value) {
                        if (in_array($attribute, ['id', 'objID'])) {
                            continue;
                        }

                        switch (gettype($value)) {
                            case 'array':
                                if (array_key_exists('ref_title', $value)) {
                                    $value = $value['ref_title'];
                                } elseif (array_key_exists('title', $value)) {
                                    $value = $value['title'];
                                } else {
                                    $values = [];

                                    foreach ($value as $subObject) {
                                        if (is_array($subObject) &&
                                            array_key_exists('title', $subObject)) {
                                            $values[] = $subObject['title'];
                                        }
                                    }

                                    $value = implode(', ', $values);
                                }
                                break;
                            case 'string':
                                // Rich text editor uses HTML:
                                $value = strip_tags($value);
                                break;
                            default:
                                break;
                        }

                        if (!isset($value) || $value === '') {
                            $value = '-';
                        }

                        IO::out(
                            '%s: %s',
                            $categoryInfo['properties'][$attribute]['title'],
                            $value
                        );
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Shows usage of this command
     *
     * @return self Returns itself
     */
    public function showUsage(): self {
        $this->log->info(
            'Usage: %1$s %2$s [OPTIONS] [QUERY]

%3$s

QUERY could be any object title or identifier.

Examples:

1) %1$s %2$s myserver
2) %1$s %2$s "My Server"
3) %1$s %2$s 42',
            $this->config['args'][0],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
