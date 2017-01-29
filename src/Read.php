<?php

/**
 * Copyright (C) 2017 Benjamin Heisig
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

namespace bheisig\idoitcli;

use bheisig\idoitapi\API;
use bheisig\idoitapi\CMDBObjects;
use bheisig\idoitapi\CMDBCategory;

class Read extends Command {

    public function setup() {
        parent::setup();

        if ($this->isCached() === false) {
            throw new \Exception('Unsufficient data. Please run "idoitcli init" first.', 400);
        }

        $this->api = new API($this->config['api']);

        $this->api->login();

        return $this;
    }

    public function execute() {
        $path = end($this->config['args']);

        if (count($GLOBALS['argv']) == 2) {
            $path = '';
        }

        $parts = explode('/', $path);

        $objectTypes = $this->getObjectTypes();

        $objectTypeConst = null;

        foreach ($objectTypes as $objectType) {
            if (strtolower($objectType['title']) === strtolower($parts[0])) {
                $objectTypeConst = $objectType['const'];
                break;
            }
        }

        $cmdbObjects = new CMDBObjects($this->api);

        switch (count($parts)) {
            case 1:
                if (in_array($parts[0], ['*', ''])) {
                    switch (count($objectTypes)) {
                        case 1:
                            IO::err('Found 1 object type');
                            break;
                        default:
                            IO::err('Found %s object types', count($objectTypes));
                            break;
                    }

                    IO::err('');

                    foreach ($objectTypes as $objectType) {
                        IO::out($objectType['title']);
                    }
                } else if (isset($objectTypeConst)) {
                    $objects = $cmdbObjects->readByType($objectTypeConst);

                    switch (count($objects)) {
                        case 0:
                            IO::err('Unknown object');
                            break;
                        case 1:
                            IO::err('Found 1 object');
                            break;
                        default:
                            IO::err('Found %s objects', count($objects));
                            break;
                    }

                    IO::err('');

                    foreach ($objects as $object) {
                        IO::out($object['title']);
                    }
                } else {
                    $objects = $cmdbObjects->read(['title' => $parts[0]]);

                    switch (count($objects)) {
                        case 0:
                            IO::err('Unknown object');
                            break 2;
                        case 1:
                            IO::err('Found 1 object');
                            break;
                        default:
                            IO::err('Found %s objects', count($objects));
                            break;
                    }

                    foreach ($objects as $object) {
                        IO::err('');
                        $this->formatObject($object);
                    }
                }

                break;
            case 2:
                if (isset($objectTypeConst)) {
                    $objects = $cmdbObjects->read(['type' => $objectTypeConst, 'title' => $parts[1]]);

                    switch (count($objects)) {
                        case 0:
                            IO::err('Unknown object');
                            break 2;
                        case 1:
                            IO::err('Found 1 object');
                            break;
                        default:
                            IO::err('Found %s objects', count($objects));
                            break;
                    }

                    foreach ($objects as $object) {
                        IO::err('');
                        $this->formatObject($object);
                    }
                } else {
                    $objects = $cmdbObjects->read(['title' => $parts[0]]);

                    $this->formatCategory($objects, $parts[1]);
                }

                break;
            case 3:
                if (isset($objectTypeConst)) {
                    $objects = $cmdbObjects->read(['type' => $objectTypeConst, 'title' => $parts[1]]);

                    $this->formatCategory($objects, $parts[2]);
                } else {
                    $objects = $cmdbObjects->read(['title' => $parts[0]]);

                    $this->formatAttribute($objects, $parts[1], $parts[2]);
                }
                break;
            case 4:
                if (isset($objectTypeConst)) {
                    $objects = $cmdbObjects->read(['type' => $objectTypeConst, 'title' => $parts[1]]);

                    $this->formatAttribute($objects, $parts[2], $parts[3]);
                } else {
                    throw new \Exception('Bad request');
                }
                break;
            default:
                throw new \Exception('Bad request');
        }

        return $this;
    }

    public function tearDown () {
        $this->api->logout();

        return parent::tearDown();
    }

    protected function formatObjects($objects) {

    }

    protected function formatObject($object) {
        IO::out('Title: %s', $object['title']);
        IO::out('ID: %s', $object['id']);
        IO::out('Type: %s', $object['type_title']);
    }

    protected function formatCategory($objects, $category) {
        switch (count($objects)) {
            case 0:
                IO::err('Unknown object');
                return $this;
            case 1:
                IO::err('Found 1 object');
                break;
            default:
                IO::err('Found %s objects', count($objects));
                break;
        }

        $categories = $this->getCategories();

        $identifiedCategory = [];

        foreach ($categories as $categoryInfo) {
            if (strtolower($categoryInfo['title']) === strtolower($category)) {
                $identifiedCategory = $categoryInfo;
                break;
            }
        }

        if (!isset($identifiedCategory)) {
            IO::err('Unknown category');
            return $this;
        }

        $cmdbCategory = new CMDBCategory($this->api);

        $objectIDs = [];

        foreach ($objects as $object) {
            $objectIDs[] = $object['id'];
        }

        $batchEntries = $cmdbCategory->batchRead($objectIDs, [$identifiedCategory['const']]);

        $counter = 0;

        foreach ($objects as $object) {
            if (count($objects) > 1) {
                IO::err('');
                $this->formatObject($object);
                IO::err('');
            }

            switch(count($batchEntries[$counter])) {
                case 0:
                    IO::err('No entries found');
                    break;
                case 1:
                    IO::err('Found 1 entry');
                    break;
                default:
                    IO::err('Found %s entries', count($batchEntries[$counter]));
                    break;
            }

            foreach ($batchEntries[$counter] as $entry) {
                IO::err('');

                foreach ($identifiedCategory['properties'] as $attribute => $attributeInfo) {
                    if (in_array($attribute, ['id', 'objID'])) {
                        continue;
                    }

                    switch (gettype($entry[$attribute])) {
                        case 'array':
                            if (array_key_exists('title', $entry[$attribute])) {
                                $value = $entry[$attribute]['title'];
                            } else {
                                $value = '...';
                            }
                            break;
                        default:
                            $value = $entry[$attribute];
                            break;
                    }

                    if (!isset($value) || $value === '') {
                        $value = '-';
                    }

                    IO::out(
                        '%s: %s',
                        $attributeInfo['title'],
                        $value
                    );
                }
            }

            $counter++;
        }

        return $this;
    }

    protected function formatAttribute($objects, $category, $attribute) {
        switch (count($objects)) {
            case 0:
                IO::err('Unknown object');
                return $this;
            case 1:
                IO::err('Found 1 object');
                break;
            default:
                IO::err('Found %s objects', count($objects));
                break;
        }

        $categories = $this->getCategories();

        $identifiedCategory = [];

        foreach ($categories as $categoryInfo) {
            if (strtolower($categoryInfo['title']) === strtolower($category)) {
                $identifiedCategory = $categoryInfo;
                break;
            }
        }

        if (!isset($identifiedCategory)) {
            IO::err('Unknown category');
            return $this;
        }

        $cmdbCategory = new CMDBCategory($this->api);

        $objectIDs = [];

        foreach ($objects as $object) {
            $objectIDs[] = $object['id'];
        }

        $batchEntries = $cmdbCategory->batchRead($objectIDs, [$identifiedCategory['const']]);

        $counter = 0;

        foreach ($objects as $object) {
            if (count($objects) > 1) {
                IO::err('');
                $this->formatObject($object);
                IO::err('');
            }

            switch(count($batchEntries[$counter])) {
                case 0:
                    IO::err('No entries found');
                    break;
                case 1:
                    IO::err('Found 1 entry');
                    break;
                default:
                    IO::err('Found %s entries', count($batchEntries[$counter]));
                    break;
            }

            IO::err('');

            foreach ($batchEntries[$counter] as $entry) {
                foreach ($identifiedCategory['properties'] as $attributeKey => $attributeInfo) {
                    if ($attribute !== $attributeKey) {
                        continue;
                    }

                    switch (gettype($entry[$attributeKey])) {
                        case 'array':
                            if (array_key_exists('title', $entry[$attributeKey])) {
                                $value = $entry[$attributeKey]['title'];
                            } else {
                                $value = '...';
                            }
                            break;
                        default:
                            $value = $entry[$attributeKey];
                            break;
                    }

                    if (!isset($value) || $value === '') {
                        $value = '-';
                    }

                    IO::out(
                        '%s: %s',
                        $attributeInfo['title'],
                        $value
                    );
                }
            }

            $counter++;
        }

        return $this;
    }

    public function showUsage() {
        IO::out('Usage: idoit [OPTIONS] read [PATH]

Path:

Wildcards:

Examples:

');
    }

}
