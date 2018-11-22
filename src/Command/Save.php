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

use bheisig\idoitcli\Service\Cache;

/**
 * Command "save"
 */
class Save extends Command {

    use Cache;

    protected $objectTypeID;
    protected $objectTypeConstant;
    protected $objectTypeTitle;

    protected $objectID;
    protected $objectTitle;

    protected $categoryID;
    protected $categoryConstant;
    protected $categoryTitle;

    protected $attributes = [];

    protected $entryID;
    protected $entry;

    /**
     * Process some routines before execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function setup(): Command {
        parent::setup();

        if ($this->isCached() === false) {
            throw new \RuntimeException(sprintf(
                'Unsufficient data. Please run "%s cache" first.',
                $this->config['composer']['extra']['name']
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
        $this->log->info($this->getDescription());

        $query = $this->getQuery();

        $this
            ->parseQuery($query)
            ->analyseCollectedData();

        return $this;
    }

    /**
     * Try to identify what the user wants to do
     *
     * @param string $query Query
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function parseQuery(string $query): self {
        $queryParts = explode('/', $query);

        switch (count($queryParts)) {
            case 0:
                /**
                 * We're completely in interactive mode.
                 */
                break;
            case 1:
                /**
                 * Either it's an object type…
                 *
                 * $ idoitcli save server
                 *
                 * …or an object title:
                 *
                 * $ idoitcli save mylittleserver
                 */
                if ($this->identifyObjectType($queryParts[0]) === false) {
                    $this->identifyObject($queryParts[0]);
                }
                break;
            case 2:
                /**
                 * Either it's either a combination of…
                 *
                 * …object type and title:
                 *
                 * $ idoitcli save server/mylittleserver
                 *
                 * …or object title and category:
                 *
                 * $ idoitcli save mylittleserver/model
                 */
                if ($this->identifyObjectType($queryParts[0]) === false) {
                    $this->identifyObject($queryParts[0]);
                    $this->identifyCategory($queryParts[1]);
                } else {
                    $this->identifyObject($queryParts[1]);
                }
                break;
            case 3:
                /**
                 * It's a either combination of…
                 *
                 * …object type, object title and category name:
                 *
                 * $ idoitcli server/mylittleserver/model
                 *
                 * …or object title, category name and entry identifier:
                 *
                 * $ idoitcli mylittleserver/hostaddress/1
                 */
                if ($this->identifyObjectType($queryParts[0]) === false) {
                    $this->identifyObject($queryParts[0]);
                    $this->identifyCategory($queryParts[1]);
                    $this->identifyEntry($queryParts[2]);
                } else {
                    $this->identifyObject($queryParts[1]);
                    $this->identifyCategory($queryParts[2]);
                }
                break;
            case 4:
                /**
                 * It's a combination of
                 * object type, object title, category name
                 * and entry identifier:
                 *
                 * $ idoitcli server/mylittleserver/hostaddress/1
                 */
                if ($this->identifyObjectType($queryParts[0]) === false) {
                    throw new \BadMethodCallException(sprintf(
                        'Unknown object type "%s"',
                        $queryParts[0]
                    ));
                }

                $this->identifyObject($queryParts[1]);
                $this->identifyCategory($queryParts[2]);
                $this->identifyEntry($queryParts[3]);
                break;
            default:
                throw new \BadMethodCallException(sprintf(
                    'Query "%s" is invalid',
                    $query
                ));
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function analyseCollectedData(): self {
        if ($this->hasObjectType()) {
            $this->log->debug(
                'Object type identified: %s [%s]',
                $this->objectTypeTitle,
                $this->objectTypeConstant
            );
        } else {
            $this->log->debug('No object type identified');
        }

        if ($this->hasObject()) {
            $this->log->debug(
                'Object identified: %s [%s]',
                $this->objectTitle,
                $this->objectID
            );
        } else {
            $this->log->debug('No object identified');
        }

        if ($this->hasCategory()) {
            $this->log->debug(
                'Category identified : %s [%s]',
                $this->categoryTitle,
                $this->categoryConstant
            );
        } else {
            $this->log->debug('No category identified');
        }

        if ($this->hasObjectType() && $this->hasCategory()) {
            if ($this->isCategoryAssignedToObjectType($this->objectTypeConstant, $this->categoryConstant) === false) {
                throw new \BadMethodCallException(sprintf(
                    'Category "%s" [%s] is not assigned to object type "%s" [%s]',
                    $this->categoryTitle,
                    $this->categoryConstant,
                    $this->objectTypeTitle,
                    $this->objectTypeConstant
                ));
            }
        }

        if ($this->hasEntry()) {
            $this->log->debug(
                'Category entry identified by ID: %s',
                $this->entryID
            );
        } else {
            $this->log->debug('No category entry identifier');
        }

        return $this;
    }

    /**
     * Try to identify object type
     *
     * If found set…
     * @see $objectTypeConstant
     * @see $objectTypeID
     * @see $objectTypeTitle
     *
     * @param string $candidate Localized name, constant or numeric identifier
     *
     * @return bool Returns true if found, otherwise false
     *
     * @throws \Exception on error
     */
    protected function identifyObjectType(string $candidate): bool {
        $objectTypes = $this->getObjectTypes();

        if (is_numeric($candidate) && (int) $candidate > 0) {
            $candidateID = (int) $candidate;

            foreach ($objectTypes as $objectType) {
                if ((int) $objectType['id'] === $candidateID) {
                    $this->objectTypeConstant = $objectType['const'];
                    $this->objectTypeID = (int) $objectType['id'];
                    $this->objectTypeTitle = $objectType['title'];
                    return true;
                }
            }
        } else {
            foreach ($objectTypes as $objectType) {
                if (strtolower($objectType['title']) === strtolower($candidate)) {
                    $this->objectTypeConstant = $objectType['const'];
                    $this->objectTypeID = (int) $objectType['id'];
                    $this->objectTypeTitle = $objectType['title'];
                    return true;
                } elseif (strtolower($objectType['const']) === strtolower($candidate)) {
                    $this->objectTypeConstant = $objectType['const'];
                    $this->objectTypeID = (int) $objectType['id'];
                    $this->objectTypeTitle = $objectType['title'];
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Try to identify object
     *
     * If found set…
     * @see $objectID
     * @see $objectTitle
     *
     * Also set object type if needed by using
     * @see identifyObjectType
     *
     * @param string $candidate Title or numeric identifier
     *
     * @return bool Returns true if found, otherwise false
     *
     * @throws \Exception on error
     */
    protected function identifyObject(string $candidate): bool {
        if ($this->hasObjectType() && is_numeric($candidate) && (int) $candidate > 0) {
            $objects = $this->useIdoitAPI()->fetchObjects([
                'ids' => [(int) $candidate],
                'type' => $this->objectTypeID
            ]);

            if (count($objects) === 1) {
                $object = end($objects);
                $this->objectID = (int) $object['id'];
                $this->objectTitle = $object['title'];
                return true;
            }
        } elseif (!$this->hasObjectType() && is_numeric($candidate) && (int) $candidate > 0) {
            $object = $this->useIdoitAPI()->getCMDBObject()->read((int) $candidate);

            if (count($object) > 0) {
                $this->objectID = (int) $object['id'];
                $this->objectTitle = $object['title'];

                $this->identifyObjectType((string) $object['type']);
                return true;
            }
        } elseif ($this->hasObjectType()) {
            $objects = $this->useIdoitAPI()->fetchObjects([
                'title' => $candidate,
                'type' => $this->objectTypeID
            ]);

            switch (count($objects)) {
                case 0:
                    return false;
                case 1:
                    $object = end($objects);
                    $this->objectID = (int) $object['id'];
                    $this->objectTitle = $object['title'];
                    return true;
                default:
                    throw new \RuntimeException(sprintf(
                        'Object title "%s" is ambiguous',
                        $candidate
                    ));
            }
        } else {
            $objects = $this->useIdoitAPI()->fetchObjects([
                'title' => $candidate
            ]);

            switch (count($objects)) {
                case 0:
                    return false;
                case 1:
                    $object = end($objects);
                    $this->objectID = (int) $object['id'];
                    $this->objectTitle = $object['title'];

                    $this->identifyObjectType((string) $object['type']);
                    return true;
                default:
                    throw new \RuntimeException(sprintf(
                        'Object title "%s" is ambiguous',
                        $candidate
                    ));
            }
        }

        return false;
    }

    /**
     * Try to identify category
     *
     * If found set…
     * @see $attributes
     * @see $categoryConstant
     * @see $categoryID
     * @see $categoryTitle
     *
     * @param string $candidate Localized name, constant or numeric identifier
     *
     * @return bool Returns true if found, otherwise \Exception is thrown
     *
     * @throws \Exception on error
     */
    protected function identifyCategory(string $candidate): bool {
        $categories = $this->getCategories();

        if (is_numeric($candidate) && (int) $candidate > 0) {
            $candidateID = (int) $candidate;

            foreach ($categories as $category) {
                if ((int) $category['id'] === $candidateID) {
                    $this->attributes = $category['properties'];
                    $this->categoryConstant = $category['const'];
                    $this->categoryID = (int) $category['id'];
                    $this->categoryTitle = $category['title'];
                    return true;
                }
            }
        } else {
            foreach ($categories as $category) {
                if (strtolower($category['title']) === strtolower($candidate)) {
                    $this->attributes = $category['properties'];
                    $this->categoryConstant = $category['const'];
                    $this->categoryID = (int) $category['id'];
                    $this->categoryTitle = $category['title'];
                    return true;
                } elseif (strtolower($category['const']) === strtolower($candidate)) {
                    $this->attributes = $category['properties'];
                    $this->categoryConstant = $category['const'];
                    $this->categoryID = (int) $category['id'];
                    $this->categoryTitle = $category['title'];
                    return true;
                }
            }
        }

        throw new \BadMethodCallException(sprintf(
            'Unknown category "%s"',
            $candidate
        ));
    }

    /**
     * Try to identify category entry
     *
     * @param string $candidate
     *
     * @return bool Returns true, otherwise \Exception is thrown
     *
     * @throws \Exception on error
     */
    protected function identifyEntry(string $candidate): bool {
        if (!$this->hasObject()) {
            throw new \BadMethodCallException(
                'Unknown objects cannot have category entries'
            );
        }

        if (!$this->hasCategory()) {
            throw new \BadMethodCallException(
                'Unknown categories cannot have entries'
            );
        }

        if (!is_numeric($candidate) && (int) $candidate <= 0) {
            throw new \BadMethodCallException(sprintf(
                'Category entry "%s" is not a valid numeric identifier',
                $candidate
            ));
        }

        $this->entryID = (int) $candidate;

        $this->entry = $this->useIdoitAPI()->getCMDBCategory()->readOneByID(
            $this->objectID,
            $this->categoryConstant,
            $this->entryID
        );

        return true;
    }

    /**
     * Is category really assigned to object type?
     *
     * @param string $objectTypeConstant Object type constant
     * @param string $categoryConstant Category constant
     *
     * @return bool Returns true if found, otherwise false
     *
     * @throws \Exception on error
     */
    protected function isCategoryAssignedToObjectType(string $objectTypeConstant, string $categoryConstant): bool {
        $assignedCategories = $this->getAssignedCategories($objectTypeConstant);

        $types = ['catg', 'cats', 'custom'];

        foreach ($types as $type) {
            if (!array_key_exists($type, $assignedCategories)) {
                continue;
            }

            foreach ($assignedCategories[$type] as $category) {
                if ($categoryConstant === $category['const']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Has query an object type?
     *
     * @return bool
     */
    protected function hasObjectType(): bool {
        return isset($this->objectTypeID);
    }

    /**
     * Has query an object?
     *
     * @return bool
     */
    protected function hasObject(): bool {
        return isset($this->objectID);
    }

    /**
     * Has query a category?
     *
     * @return bool
     */
    protected function hasCategory(): bool {
        return isset($this->categoryID);
    }

    /**
     * Has query an entry identifier?
     *
     * @return bool
     */
    protected function hasEntry(): bool {
        return isset($this->entryID);
    }

    /**
     * Shows usage of command
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
    QUERY   <dim>Combination of</dim> <u>type/object/category/entry</u>
    
            <u>type</u>     <dim>is the localized name of an object type,</dim>
                     <dim>its constant or its numeric identifier</dim>
            <u>object</u>   <dim>title of numeric identifier</dim>
            <u>category</u> <dim>is the localized name of the category,</dim>
                     <dim>its contant or numeric identifier</dim>
            <u>entry</u>    <dim>is the numeric identifier</dim>
                     <dim>of an existing category entry</dim>

<strong>COMMAND OPTIONS</strong>

    -a <u>ATTRIBUTE=VALUE</u>,         <dim>Localized attribute name ATTRIBUTE</dim>
    --attribute=<u>ATTRIBUTE=VALUE</u> <dim>and its value VALUE</dim>

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
    <dim># Create new object with type "server" and title "mylittleserver":</dim>
    \$ %1\$s %2\$s server/mylittleserver

    <dim># Create/update attributes in a single-value category</dim>
    \$ %1\$s %2\$s server/mylittleserver/model \\
        -a manufacturer=A -a title=123
    \$ %1\$s %2\$s server/mylittleserver/location \\
        -a ru=11

    <dim># Update attributes in a multi-value category</dim>
    \$ %1\$s %2\$s server/mylittleserver/hostaddress/1 \\
        -a ipv4address=192.168.42.23

    <dim># Interactive mode (based on templates)</dim>
    \$ %1\$s %2\$s
    Create new object
    Type? server
    Title? mylittleserver
    Add more attributes [y/N]? y
    [Model] Manufacturer? A
    [Model] Model? 123
    [Hostaddress] Hostname? mylittleserver
    [Location] Location? rackXY
    […]
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
