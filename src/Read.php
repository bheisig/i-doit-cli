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

use bheisig\idoitapi\CMDBObject;
use bheisig\idoitapi\CMDBObjects;
use bheisig\idoitapi\CMDBCategory;

/**
 * Command "read"
 */
class Read extends Command {

    /**
     * @var \bheisig\idoitapi\CMDBObjects
     */
    protected $cmdbObjects;

    /**
     * @var \bheisig\idoitapi\CMDBCategory
     */
    protected $cmdbCategory;

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
            throw new \Exception(sprintf(
                'Unsufficient data. Please run "%s init" first.',
                $this->config['basename']
            ), 500);
        }

        $this->initiateAPI();

        $this->api->login();

        $this->cmdbObjects = new CMDBObjects($this->api);
        $this->cmdbCategory = new CMDBCategory($this->api);

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
                    $objects = $this->fetchObjects(['type' => $objectTypeConst]);

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
                } else if (is_numeric($parts[0])) {
                    /**
                     * Show common information about an object by its identifier
                     *
                     * Examples:
                     *
                     * idoit read 9
                     */
                    $objectID = (int) $parts[0];

                    if ($objectID === 0) {
                        throw new \Exception(sprintf(
                            'Unable to find object by "%s". Please specify a valid object identifier',
                            $parts[0]
                        ));
                    }

                    $cmdbObject = new CMDBObject($this->api);

                    $result = $cmdbObject->read($objectID);

                    if (count($result) === 0) {
                        IO::err('Unknown object');
                    } else {
                        IO::err('Found 1 object');
                        IO::err('');
                        $this->printTitle([$result]);
                    }
                } else {
                    /**
                     * Show common information about an object by its title
                     *
                     * Examples:
                     *
                     * idoit read host.example.net
                     */
                    $objects = $this->fetchObjects(['title' => $parts[0]]);

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
                        $objects = $this->fetchObjects(['type' => $objectTypeConst]);

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
                        $objects = $this->fetchObjects(['type' => $objectTypeConst, 'title' => $parts[1]]);


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
                    $objects = $this->fetchObjects(['title' => $parts[0]]);

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
                        $objects = $this->fetchObjects(['type' => $objectTypeConst, 'title' => $parts[1]]);

                        $this->formatCategory($objects, $parts[2]);
                    }
                } else {
                    $objects = $this->fetchObjects(['title' => $parts[0]]);

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
                        $objects = $this->fetchObjects(['type' => $objectTypeConst, 'title' => $parts[1]]);

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

    /**
     * Formats common information about an object
     *
     * @param array $object Associative array
     *
     * @return self Returns itself
     */
    protected function formatObject($object) {
        IO::out('Title: %s', $object['title']);
        IO::out('ID: %s', $object['id']);
        IO::out('Type: %s', $object['type_title']);

        return $this;
    }

    /**
     * Formats attributes of one or more objects
     *
     * @param array $objects Associative array
     * @param string $category Category title
     *
     * @return self Returns itself
     */
    protected function formatCategory(array $objects, $category) {
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

        $objectIDs = [];

        foreach ($objects as $object) {
            $objectIDs[] = $object['id'];
        }

        $batchEntries = $this->fetchCategoryEntries($objectIDs, $identifiedCategory['const']);

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

                    // Rich text editor uses HTML:
                    $value = strip_tags($value);

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

    /**
     * Formats an attributes for one or more objects
     *
     * @param array $objects Associative array
     * @param string $category Category title
     * @param string $attribute Attribute title
     *
     * @return self Returns itself
     */
    protected function formatAttribute(array $objects, $category, $attribute) {
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

        $objectIDs = [];

        foreach ($objects as $object) {
            $objectIDs[] = $object['id'];
        }

        $batchEntries = $this->fetchCategoryEntries(
            $objectIDs,
            $identifiedCategory['const']
        );

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

                    // Rich text editor uses HTML:
                    $value = strip_tags($value);

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

    /**
     * Formats list of assigned categories
     *
     * @param array $assignedCategories Indexed array of associative arrays
     *
     * @return self Returns itself
     */
    protected function formatAssignedCategories(array $assignedCategories) {
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

    /**
     * Formats list of attributes which belong to category which is assigned to an object type
     *
     * @param string $objectType Object type constant
     * @param string $categoryTitle Category title
     *
     * @throws \Exception on error
     *
     * @return self Returns itself
     */
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

        return $this;
    }

    /**
     * Fetches objects from i-doit
     *
     * @param array $filter Associative array, see CMDBObjects::read()
     *
     * @return array Indexed array of associative arrays
     *
     * @throws \Exception on error
     */
    protected function fetchObjects($filter) {
        $limit = $this->config['limitBatchRequests'];

        $objects = [];
        $offset = 0;
        $counter = 0;

        while (true) {
            if ($limit > 0) {
                $result = $this->cmdbObjects->read($filter, $limit, $offset);

                $offset += $limit;
                $counter++;

                if ($counter === 3) {
                    IO::err('This could take a while…');
                }
            } else {
                $result = $this->cmdbObjects->read($filter, $limit, $offset);
            }

            if (count($result) === 0) {
                break;
            }

            foreach ($result as $object) {
                $objects[] = [
                    'id' => $object['id'],
                    'title' => $object['title'],
                    'type' => $object['type'],
                    'type_title' => $object['type_title'],
                ];
            }

            if (count($result) < $limit) {
                break;
            }

            if ($limit === 0) {
                break;
            }
        }

        return $objects;
    }

    /**
     * Fetches category entries of one category for one or more objects
     *
     * @param int[] $objectIDs List of object identifiers
     * @param string $categoryConst Category constant
     *
     * @return array Indexed array of associative arrays
     *
     * @throws \Exception on error
     */
    protected function fetchCategoryEntries(array $objectIDs, $categoryConst) {
        $limit = $this->config['limitBatchRequests'];

        if ($limit === 0) {
            return $this->cmdbCategory->batchRead(
                $objectIDs,
                [$categoryConst]
            );
        }

        $entries = [];
        $offset = 0;
        $counter = 0;

        while (true) {
            $counter++;

            if ($counter === 3) {
                IO::err('This could take a while…');
            }

            $slicedObjectIDs = array_slice(
                $objectIDs,
                $offset,
                $limit
            );

            $result = $this->cmdbCategory->batchRead(
                $slicedObjectIDs,
                [$categoryConst]
            );

            if (count($result) === 0) {
                break;
            }

            $offset += $limit;

            $entries = array_merge($entries, $result);

            if (count($result) < $limit) {
                break;
            }
        }

        return $entries;
    }

    /**
     * Prints item titles
     *
     * @param array $items Indexed array of associative arrays
     *
     * @return self Returns itself
     */
    protected function printTitle(array $items) {
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
        $command = strtolower((new \ReflectionClass($this))->getShortName());

        IO::out('Usage: %1$s [OPTIONS] %2$s [PATH]

%3$s

List object types:

    idoit %2$s
    idoit %2$s /

List objects:

    idoit %2$s server
    idoit %2$s server/
    idoit %2$s server/*

Show common information about an object by its title:

    idoit %2$s server/host.example.net
    idoit %2$s host.example.net

Show common information about an object by its identifier:

    idoit %2$s 42

List assigned categories:

    idoit %2$s server/host.example.net/
    idoit %2$s server/host.example.net/*
    idoit %2$s host.example.net/
    idoit %2$s host.example.net/*

List category attributes:

    idoit %2$s server/host.example.net/model/
    idoit %2$s server/host.example.net/model/*
    idoit %2$s host.example.net/model/
    idoit %2$s host.example.net/model/*

Show category entries:

    idoit %2$s server/host.example.net/model
    idoit %2$s host.example.net/model

Show atttribute value:

    idoit %2$s server/host.example.net/model/model
    idoit %2$s host.example.net/model/model

These examples work great with unique names. That is why it is common practice
to give objects unique titles that are not in conflict with object types and
categories.',
            $this->config['basename'],
            $command,
            $this->config['commands'][$command]
        );

        return $this;
    }

}
