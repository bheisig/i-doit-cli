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

use bheisig\idoitapi\CMDBObjects;
use bheisig\idoitapi\CMDBCategory;

/**
 * Command "read"
 */
class Read extends Command {

    /**
     * Processes some routines before the execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function setup() {
        parent::setup();

        if ($this->isCached() === false) {
            throw new \Exception('Unsufficient data. Please run "idoitcli init" first.', 400);
        }

        $this->initiateAPI();

        $this->api->login();

        return $this;
    }

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute() {
        $path = $this->getQuery();

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
                    /**
                     * List object types
                     *
                     * Examples:
                     *
                     * idoit read
                     */
                    switch (count($objectTypes)) {
                        case 1:
                            IO::err('Found 1 object type');
                            break;
                        default:
                            IO::err('Found %s object types', count($objectTypes));
                            break;
                    }

                    IO::err('');

                    $this->printTitle($objectTypes);
                } else if (isset($objectTypeConst)) {
                    /**
                     * List objects
                     *
                     * Examples:
                     *
                     * idoit read server
                     */
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

                    $this->printTitle($objects);
                } else {
                    /**
                     * Show common information about an object
                     *
                     * Examples:
                     *
                     * idoit read host.example.net
                     */
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
                if ($path === '/') {
                    /**
                     * List object types
                     *
                     * Examples:
                     *
                     * idoit read /
                     */
                    switch (count($objectTypes)) {
                        case 1:
                            IO::err('Found 1 object type');
                            break;
                        default:
                            IO::err('Found %s object types', count($objectTypes));
                            break;
                    }

                    IO::err('');

                    $this->printTitle($objectTypes);
                } else if (isset($objectTypeConst)) {
                    if (in_array($parts[1], ['', '*'])) {
                        /**
                         * List objects
                         *
                         * Examples:
                         *
                         * idoit read server/
                         * idoit read server/*
                         */
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

                        $this->printTitle($objects);
                    } else {
                        /**
                         * Show common information about an object
                         *
                         * Examples:
                         *
                         * idoit read server/host.example.net
                         */
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
                    }
                } else {
                    $objects = $cmdbObjects->read(['title' => $parts[0]]);

                    if (in_array($parts[1], ['', '*'])) {
                        /**
                         * List assigned categories
                         *
                         * Examples:
                         *
                         * idoit read host.example.net/
                         * idoit read host.example.net/*
                         */
                        switch (count($objects)) {
                            case 0:
                                throw new \Exception('Unknown object');
                            case 1:
                                IO::err('Assigned categories');
                                IO::err('');
                                break;
                            default:
                                throw new \Exception('Found %s objects', count($objects));
                                break;
                        }

                        $found = false;

                        foreach ($objectTypes as $objectType) {
                            if ($objectType['id'] === $objects[0]['type']) {
                                $assignedCategories = $this->getAssignedCategories($objectType['const']);

                                $this->formatAssignedCategories($assignedCategories);

                                $found = true;

                                break;
                            }
                        }

                        if ($found === false) {
                            throw new \Exception(sprintf(
                                'Object "%s" [%s] has unknown object type with identifier %s. Cache seems to be outdated. Please re-run "idoit init".',
                                $objects[0]['title'],
                                $objects[0]['id'],
                                $objects[0]['type']
                            ));
                        }
                    } else {
                        /**
                         * Show category entries
                         *
                         * Examples:
                         *
                         * idoit read host.example.net/model
                         */
                        $this->formatCategory($objects, $parts[1]);
                    }
                }

                break;
            case 3:
                if (isset($objectTypeConst)) {
                    if (in_array($parts[2], ['', '*'])) {
                        /**
                         * List  assigned categories
                         *
                         * Examples:
                         *
                         * idoit read server/host.example.net/
                         * idoit read server/host.example.net/*
                         *
                         */
                        $assignedCategories = $this->getAssignedCategories($objectTypeConst);

                        $this->formatAssignedCategories($assignedCategories);
                    } else {
                        /**
                         * Show category entries
                         *
                         * Examples:
                         *
                         * idoit read server/host.example.net/model
                         */
                        $objects = $cmdbObjects->read(['type' => $objectTypeConst, 'title' => $parts[1]]);

                        $this->formatCategory($objects, $parts[2]);
                    }
                } else {
                    $objects = $cmdbObjects->read(['title' => $parts[0]]);

                    if (in_array($parts[2], ['', '*'])) {
                        /**
                         * List attributes
                         *
                         * Examples:
                         *
                         * idoit read host.example.net/model/
                         * idoit read host.example.net/model/*
                         */
                        switch (count($objects)) {
                            case 0:
                                throw new \Exception('Unknown object');
                            case 1:
                                break;
                            default:
                                throw new \Exception('Found %s objects', count($objects));
                                break;
                        }

                        $found = false;

                        foreach ($objectTypes as $objectType) {
                            if ($objectType['id'] === $objects[0]['type']) {
                                $this->formatAttributes($objectType['const'], $parts[1]);

                                $found = true;

                                break;
                            }
                        }

                        if ($found === false) {
                            throw new \Exception(sprintf(
                                'Object "%s" [%s] has unknown object type with identifier %s. Cache seems to be outdated. Please re-run "idoit init".',
                                $objects[0]['title'],
                                $objects[0]['id'],
                                $objects[0]['type']
                            ));
                        }
                    } else {
                        /**
                         * Show atttribute value
                         *
                         * Examples:
                         *
                         * idoit read host.example.net/model/model
                         */
                        $this->formatAttribute($objects, $parts[1], $parts[2]);
                    }
                }
                break;
            case 4:
                if (isset($objectTypeConst)) {
                    if (in_array($parts[3], ['', '*'])) {
                        /**
                         * List attributes
                         *
                         * Examples:
                         *
                         * idoit read server/host.example.net/model/
                         * idoit read server/host.example.net/model/*
                         */
                        $this->formatAttributes($objectTypeConst, $parts[2]);
                    } else {
                        /**
                         * Show attribute value
                         *
                         * Examples:
                         *
                         * idoit read server/host.example.net/model/model
                         */
                        $objects = $cmdbObjects->read(['type' => $objectTypeConst, 'title' => $parts[1]]);

                        $this->formatAttribute($objects, $parts[2], $parts[3]);
                    }
                } else {
                    throw new \Exception('Bad request');
                }
                break;
            default:
                throw new \Exception('Bad request');
        }

        return $this;
    }

    /**
     * Processes some routines after the execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
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

        if (count($identifiedCategory) === 0) {
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
                    if (strtolower($attribute) !== strtolower($attributeInfo['title'])) {
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

    protected function formatAssignedCategories($assignedCategories) {
        $list = [];

        foreach ($assignedCategories as $types => $categories) {
            foreach ($categories as $category) {
                $list[] = $category['title'];
            }
        }

        natsort($list);

        foreach ($list as $category) {
            IO::out($category);
        }

        return $this;
    }

    protected function formatAttributes($objectType, $categoryTitle) {
        IO::err('Category attributes');
        IO::err('');

        $assignedCategories = $this->getAssignedCategories($objectType);

        $categoryConst = '';

        foreach ($assignedCategories as $type => $categories) {
            foreach ($categories as $category) {
                if (strtolower($category['title']) === strtolower($categoryTitle)) {
                    $categoryConst = $category['const'];
                    break 2;
                }
            }
        }

        if ($categoryConst === '') {
            throw new \Exception(sprintf(
                'This object type [%s] has no category "%s". Maybe you cache is outdated. Please re-run "idoit init".',
                $objectType,
                $categoryTitle
            ));
        }

        $cageoryInfo = $this->getCategoryInfo($categoryConst);

        foreach ($cageoryInfo['properties'] as $attribute) {
            IO::out($attribute['title']);
        }
    }

    protected function printTitle($items) {
        $sorted = [];

        foreach ($items as $item) {
            $sorted[] = $item['title'];
        }

        natsort($sorted);

        foreach ($sorted as $value) {
            IO::out($value);
        }

        return $this;
    }


    /**
     * Shows usage of this command
     *
     * @return self Returns itself
     */
    public function showUsage() {
        IO::out('Usage: %1$s [OPTIONS] read [PATH]

Path:

Wildcards:

Examples:

', $this->config['basename']);

        return $this;
    }

}
