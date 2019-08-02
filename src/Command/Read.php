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
use bheisig\cli\IO;

/**
 * Command "read"
 */
class Read extends Command {

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
            ->printAsOutput();

        $path = $this->config['arguments'][0];

        $parts = explode('/', $path);

        $objectTypes = $this->useCache()->getObjectTypes();

        $objectTypeConst = '';

        foreach ($objectTypes as $objectType) {
            if (strtolower($objectType['title']) === strtolower($parts[0])) {
                $objectTypeConst = $objectType['const'];
                break;
            }
        }

        switch (count($parts)) {
            case 1:
                if (in_array($parts[0], ['~', '*', ''])) {
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
                } elseif (strlen($objectTypeConst) > 0) {
                    /**
                     * List objects
                     *
                     * Examples:
                     *
                     * idoit read server
                     */
                    $objects = $this->useIdoitAPI()->fetchObjects(['type' => $objectTypeConst]);

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
                } elseif (is_numeric($parts[0])) {
                    /**
                     * Show common information about an object by its identifier
                     *
                     * Examples:
                     *
                     * idoit read 9
                     */
                    $objectID = (int) $parts[0];

                    if ($objectID === 0) {
                        throw new Exception(sprintf(
                            'Unable to find object by "%s". Please specify a valid object identifier',
                            $parts[0]
                        ));
                    }

                    $result = $this->useIdoitAPIFactory()->getCMDBObject()->read($objectID);

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
                     * idoit read *.example.net
                     * idoit read host.*.net
                     * idoit read *.*.net
                     * idoit read host*
                     */
                    $objects = $this->useIdoitAPI()->fetchObjects(['title' => $parts[0]]);

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
                } elseif (strlen($objectTypeConst) > 0) {
                    /**
                     * List objects
                     *
                     * Examples:
                     *
                     * idoit read server/
                     * idoit read server/host.example.net
                     * idoit read server/*.example.net
                     * idoit read server/host.*.net
                     * idoit read server/*.*.net
                     * idoit read server/host*
                     * idoit read server/*
                     */
                    $objects = $this->useIdoitAPI()->fetchObjects(['type' => $objectTypeConst]);

                    if (!in_array($parts[1], [''])) {
                        $objects = array_filter($objects, function ($object) use ($parts) {
                            return fnmatch($parts[1], $object['title']);
                        });
                    }

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

                    IO::err('');

                    $this->printTitle($objects);
                } else {
                    $objects = $this->useIdoitAPI()->fetchObjects(['title' => $parts[0]]);

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
                                throw new Exception('Unknown object');
                            case 1:
                                IO::err('Assigned categories');
                                IO::err('');
                                break;
                            default:
                                throw new Exception('Found %s objects', count($objects));
                                break;
                        }

                        $found = false;

                        $object = end($objects);

                        foreach ($objectTypes as $objectType) {
                            if ((int) $objectType['id'] === $object['type']) {
                                $assignedCategories = $this->useCache()->getAssignedCategories($objectType['const']);

                                $this->formatAssignedCategories($assignedCategories);

                                $found = true;

                                break;
                            }
                        }

                        if ($found === false) {
                            $this->log->error(
                                'Object "%s" [%s] has unknown object type with identifier %s.',
                                $object['title'],
                                $object['id'],
                                $object['type']
                            );

                            throw new Exception(sprintf(
                                'Unsufficient data. Please run "%s cache" first.',
                                $this->config['composer']['extra']['name']
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
                if (strlen($objectTypeConst) > 0) {
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
                        $assignedCategories = $this->useCache()->getAssignedCategories($objectTypeConst);

                        $this->formatAssignedCategories($assignedCategories);
                    } else {
                        /**
                         * Show category entries
                         *
                         * Examples:
                         *
                         * idoit read server/host.example.net/model
                         * idoit read server/*.example.net/model
                         * idoit read server/host.*.net/model
                         * idoit read server/*.*.net/model
                         * idoit read server/host*\/model
                         * idoit read server/*\/model
                         */
                        $objects = $this->useIdoitAPI()->fetchObjects(
                            ['type' => $objectTypeConst, 'title' => $parts[1]]
                        );

                        $this->formatCategory($objects, $parts[2]);
                    }
                } else {
                    $objects = $this->useIdoitAPI()->fetchObjects(['title' => $parts[0]]);

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
                                throw new Exception('Unknown object');
                            case 1:
                                break;
                            default:
                                throw new Exception('Found %s objects', count($objects));
                                break;
                        }

                        $found = false;

                        $object = end($objects);

                        foreach ($objectTypes as $objectType) {
                            if ((int) $objectType['id'] === $object['type']) {
                                $this->formatAttributes($objectType['const'], $parts[1]);

                                $found = true;

                                break;
                            }
                        }

                        if ($found === false) {
                            $this->log->error(
                                'Object "%s" [%s] has unknown object type with identifier %s.',
                                $object['title'],
                                $object['id'],
                                $object['type']
                            );

                            throw new Exception(sprintf(
                                'Unsufficient data. Please run "%s cache" first.',
                                $this->config['composer']['extra']['name']
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
                if (strlen($objectTypeConst) > 0) {
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
                        $objects = $this->useIdoitAPI()->fetchObjects(
                            ['type' => $objectTypeConst, 'title' => $parts[1]]
                        );

                        $this->formatAttribute($objects, $parts[2], $parts[3]);
                    }
                } else {
                    throw new Exception('Bad request');
                }
                break;
            default:
                throw new Exception('Bad request');
        }

        return $this;
    }

    /**
     * Formats common information about an object
     *
     * @param array $object Associative array
     *
     * @return self Returns itself
     */
    protected function formatObject(array $object): self {
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
     *
     * @throws Exception on error
     */
    protected function formatCategory(array $objects, string $category): self {
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

        $categories = $this->useCache()->getCategories();

        $candidates = [];

        foreach ($categories as $categoryInfo) {
            if (strtolower($categoryInfo['title']) === strtolower($category)) {
                $candidates[] = $categoryInfo;
            }
        }

        switch (count($candidates)) {
            case 0:
                IO::err('Unknown category "%s"', $category);
                return $this;
            case 1:
                break;
            default:
                IO::err('');
                IO::err('Unambigious category title:');
                IO::err('');

                foreach ($candidates as $candidate) {
                    IO::err(
                        '    "%s" [%s]',
                        $candidate['title'],
                        $candidate['const']
                    );
                }

                IO::err('');
                break;
        }

        foreach ($objects as $object) {
            $identifiedCategory = [];

            if (count($objects) > 1) {
                IO::err('');
                $this->formatObject($object);
                IO::err('');
            }

            $objectTypeConstant = $this->useCache()->getObjectTypeConstantByTitle($object['type_title']);
            $assignedCategories = $this->useCache()->getAssignedCategories($objectTypeConstant);

            foreach ($assignedCategories as $type => $assignedCategoriesByType) {
                foreach ($assignedCategoriesByType as $assignedCategory) {
                    foreach ($candidates as $categoryInfo) {
                        if (strtolower($categoryInfo['title']) === strtolower($category) &&
                            $assignedCategory['const'] === $categoryInfo['const']) {
                            $identifiedCategory = $categoryInfo;
                            break;
                        }
                    }
                }
            }

            if (count($identifiedCategory) === 0) {
                IO::err(
                    'Category "%s" is not assigned to object type "%s" [%s]',
                    $category,
                    $object['type_title'],
                    $objectTypeConstant
                );

                continue;
            }

            $batchEntries = $this->fetchCategoryEntries(
                [$object['id']],
                $identifiedCategory['const']
            );

            switch (count($batchEntries[0])) {
                case 0:
                    IO::err(
                        'No entries found in category "%s" [%s]',
                        $identifiedCategory['title'],
                        $identifiedCategory['const']
                    );
                    break;
                case 1:
                    IO::err(
                        'Found 1 entry in category "%s" [%s]',
                        $identifiedCategory['title'],
                        $identifiedCategory['const']
                    );
                    break;
                default:
                    IO::err(
                        'Found %s entries in category "%s" [%s]',
                        count($batchEntries[0]),
                        $identifiedCategory['title'],
                        $identifiedCategory['const']
                    );
                    break;
            }

            foreach ($batchEntries[0] as $entry) {
                IO::err('');

                foreach ($identifiedCategory['properties'] as $attribute => $attributeInfo) {
                    if (in_array($attribute, ['id', 'objID'])) {
                        continue;
                    }

                    switch (gettype($entry[$attribute])) {
                        case 'array':
                            if (array_key_exists('title', $entry[$attribute])) {
                                $value = (string) $entry[$attribute]['title'];
                            } else {
                                $value = '...';
                            }
                            break;
                        default:
                            $value = (string) $entry[$attribute];
                            break;
                    }

                    if (strlen($value) === 0) {
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
     *
     * @throws Exception on error
     */
    protected function formatAttribute(array $objects, string $category, string $attribute): self {
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

        $categories = $this->useCache()->getCategories();

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

            switch (count($batchEntries[$counter])) {
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
                                $value = (string) $entry[$attributeKey]['title'];
                            } else {
                                $value = '...';
                            }
                            break;
                        default:
                            $value = (string) $entry[$attributeKey];
                            break;
                    }

                    if (strlen($value) === 0) {
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
    protected function formatAssignedCategories(array $assignedCategories): self {
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
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function formatAttributes(string $objectType, string $categoryTitle): self {
        IO::err('Attributes in category "%s"', $categoryTitle);
        IO::err('');

        $assignedCategories = $this->useCache()->getAssignedCategories($objectType);

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
            $this->log->error(
                'This object type [%s] has no category "%s".',
                $objectType,
                $categoryTitle
            );

            throw new Exception(sprintf(
                'Unsufficient data. Please run "%s cache" first.',
                $this->config['composer']['extra']['name']
            ));
        }

        $cageoryInfo = $this->useCache()->getCategoryInfo($categoryConst);

        foreach ($cageoryInfo['properties'] as $attribute) {
            IO::out($attribute['title']);
        }

        return $this;
    }

    /**
     * Fetches category entries of one category for one or more objects
     *
     * @param int[] $objectIDs List of object identifiers
     * @param string $categoryConst Category constant
     *
     * @return array Indexed array of associative arrays
     *
     * @throws Exception on error
     */
    protected function fetchCategoryEntries(array $objectIDs, string $categoryConst): array {
        $limit = $this->config['limitBatchRequests'];

        if ($limit === 0) {
            return $this->useIdoitAPIFactory()->getCMDBCategory()->batchRead(
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
                (int) $offset,
                $limit
            );

            $result = $this->useIdoitAPIFactory()->getCMDBCategory()->batchRead(
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
    protected function printTitle(array $items): self {
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
     * Print usage of command
     *
     * @return self Returns itself
     */
    public function printUsage(): self {
        $this->log->info(
            <<< EOF
%3\$s

<strong>USAGE</strong>
    \$ %1\$s %2\$s [OPTIONS] [QUERY]

<strong>ARGUMENTS</strong>
    QUERY   <dim>Combination of</dim> <u>type/object/category</u>

            <u>type</u>     <dim>is the localized name of an object type,</dim>
                     <dim>its constant or its numeric identifier</dim>
            <u>object</u>   <dim>title or numeric identifier</dim>
            <u>category</u> <dim>is the localized name of the category,</dim>
                     <dim>its contant or numeric identifier</dim>

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
    <dim># List object types:</dim>
    %1\$s %2\$s
    %1\$s %2\$s /

    <dim># List objects:</dim>
    %1\$s %2\$s server
    %1\$s %2\$s server/
    %1\$s %2\$s server/*
    %1\$s %2\$s server/host.example.net
    %1\$s %2\$s server/*.example.net
    %1\$s %2\$s server/host.*.net
    %1\$s %2\$s server/*
    %1\$s %2\$s server/*.*.net
    %1\$s %2\$s server/srv*

    <dim># Show common information about one or more objects by their titles:</dim>
    %1\$s %2\$s host.example.net
    %1\$s %2\$s *.example.net
    %1\$s %2\$s host.*.net
    %1\$s %2\$s *.*.net
    %1\$s %2\$s host*

    <dim># Show common information about an object by its numeric identifier:</dim>
    %1\$s %2\$s 42

    <dim># List assigned categories:</dim>
    %1\$s %2\$s server/host.example.net/
    %1\$s %2\$s server/host.example.net/*
    %1\$s %2\$s host.example.net/
    %1\$s %2\$s host.example.net/*

    <dim># List assigned categories:</dim>
    %1\$s %2\$s server/host.example.net/
    %1\$s %2\$s server/host.example.net/*
    %1\$s %2\$s host.example.net/
    %1\$s %2\$s host.example.net/*

    <dim># List category attributes:</dim>
    %1\$s %2\$s server/host.example.net/model/
    %1\$s %2\$s server/host.example.net/model/*
    %1\$s %2\$s host.example.net/model/
    %1\$s %2\$s host.example.net/model/*

    <dim># Show category entries:</dim>
    %1\$s %2\$s host.example.net/model
    %1\$s %2\$s server/host.example.net/model
    %1\$s %2\$s server/*.example.net/model
    %1\$s %2\$s server/host.*.net/model
    %1\$s %2\$s server/*.*.net/model
    %1\$s %2\$s server/host*/model
    %1\$s %2\$s server/*/model

    <dim># Show atttribute value:</dim>
    %1\$s %2\$s server/host.example.net/model/model
    %1\$s %2\$s host.example.net/model/model

These examples work great with unique names. That is why it is common practice
to give objects unique titles that are not in conflict with object types and
categories.
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
