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

namespace bheisig\idoitcli\API;

use bheisig\cli\Log;
use bheisig\idoitapi\API;
use bheisig\idoitapi\CMDBCategory;
use bheisig\idoitapi\CMDBCategoryInfo;
use bheisig\idoitapi\CMDBDialog;
use bheisig\idoitapi\CMDBObject;
use bheisig\idoitapi\CMDBObjects;
use bheisig\idoitapi\CMDBObjectTypeCategories;
use bheisig\idoitapi\CMDBObjectTypes;
use bheisig\idoitapi\Idoit as CMDB;
use bheisig\idoitapi\Subnet;

/**
 * i-doit API calls
 */
class Idoit {

    /**
     * Configuration settings
     *
     * @var array Associative array
     */
    protected $config = [];

    /**
     * API
     *
     * @var \bheisig\idoitapi\API
     */
    protected $api;

    /**
     * Logger
     *
     * @var \bheisig\cli\Log
     */
    protected $log;

    /**
     * Factory
     *
     * @var array
     */
    protected $instances = [];

    /**
     * Constructor
     *
     * @param array $config Configuration settings
     * @param Log $log Logger
     *
     * @throws \Exception when configuration settings are missing
     */
    public function __construct(array $config, Log $log) {
        $this->config = $config;
        $this->log = $log;

        try {
            $this->api = new API($this->config['api']);
            $this->api->login();
        } catch (\Exception $e) {
            throw new \Exception(
                'No proper configuration for i-doit API calls: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get API instance
     *
     * @return API
     */
    public function getAPI(): API {
        return $this->api;
    }

    /**
     * Factory
     *
     * @param string $class Class name
     *
     * @return mixed
     */
    protected function getInstanceOf($class) {
        if (!array_key_exists($class, $this->instances)) {
            $className = "bheisig\\idoitapi\\$class";
            $this->instances[$class] = new $className($this->api);
        }

        return $this->instances[$class];
    }

    /**
     * Factory for CMDB
     *
     * @return CMDB
     */
    public function getCMDB(): CMDB {
        return $this->getInstanceOf('Idoit');
    }

    /**
     * Factory for CMDBObject
     *
     * @return CMDBObject
     */
    public function getCMDBObject(): CMDBObject {
        return $this->getInstanceOf('CMDBObject');
    }

    /**
     * Factory for CMDBObjects
     *
     * @return CMDBObjects
     */
    public function getCMDBObjects(): CMDBObjects {
        return $this->getInstanceOf('CMDBObjects');
    }

    /**
     * Factory for CMDBObjectTypeCategories
     *
     * @return CMDBObjectTypeCategories
     */
    public function getCMDBObjectTypeCategories(): CMDBObjectTypeCategories {
        return $this->getInstanceOf('CMDBObjectTypeCategories');
    }

    /**
     * Factory for CMDBObjectTypes
     *
     * @return CMDBObjectTypes
     */
    public function getCMDBObjectTypes(): CMDBObjectTypes {
        return $this->getInstanceOf('CMDBObjectTypes');
    }

    /**
     * Factory for CMDBCategory
     *
     * @return CMDBCategory
     */
    public function getCMDBCategory(): CMDBCategory {
        return $this->getInstanceOf('CMDBCategory');
    }

    /**
     * Factory for CMDBCategoryInfo
     *
     * @return CMDBCategoryInfo
     */
    public function getCMDBCategoryInfo(): CMDBCategoryInfo {
        return $this->getInstanceOf('CMDBCategoryInfo');
    }

    /**
     * Factory for CMDBDialog
     *
     * @return CMDBDialog
     */
    public function getCMDBDialog(): CMDBDialog {
        return $this->getInstanceOf('CMDBDialog');
    }

    /**
     * Factory for Subnet
     *
     * @return Subnet
     */
    public function getSubnet(): Subnet {
        return $this->getInstanceOf('Subnet');
    }

    /**
     * Fetches objects from i-doit
     *
     * @param array $filter Associative array, see CMDBObjects::read()
     * @param array $categories List of category constants for more information
     * @param int $objectCount How many objects will be fetched? Defaults to 0 (ignore it)
     *
     * @return array Keys: object identifiers; values: object attributes
     *
     * @throws \Exception on error
     */
    public function fetchObjects(array $filter, array $categories = [], int $objectCount = 0): array {
        $limit = $this->config['limitBatchRequests'];

        $objects = [];
        $offset = 0;

        if (array_key_exists('title', $filter) && strpos($filter['title'], '*') !== false) {
            $readFilter = array_filter($filter, function ($key) {
                return $key !== 'title';
            }, \ARRAY_FILTER_USE_KEY);
        } else {
            $readFilter = $filter;
        }

        while (true) {
            if ($limit > 0) {
                $result = $this->getCMDBObjects()->read($readFilter, $limit, $offset);

                if ($objectCount > 0 && $objectCount >= $limit) {
                    if ($offset === 0) {
                        $this->log->debug(
                            'Fetch first %s objects from %s to %s',
                            $limit,
                            $offset + 1,
                            $offset + $limit
                        );
                    } elseif (($objectCount - $offset) === 1) {
                        $this->log->debug(
                            'Fetch last object'
                        );
                    } elseif (($objectCount - $offset) > 1 && ($offset + $limit) > $objectCount) {
                        $this->log->debug(
                            'Fetch last %s objects',
                            $objectCount - $offset
                        );
                    } elseif (($objectCount - $offset) > 1) {
                        $this->log->debug(
                            'Fetch next %s objects from %s to %s',
                            $limit,
                            $offset + 1,
                            $offset + $limit
                        );
                    }
                }

                $offset += $limit;
            } else {
                $result = $this->getCMDBObjects()->read($readFilter);
            }

            if (count($result) === 0) {
                break;
            }

            if (array_key_exists('title', $filter) && strpos($filter['title'], '*') !== false) {
                $result = array_filter($result, function ($object) use ($filter) {
                    return fnmatch($filter['title'], $object['title']);
                });
            }

            $objectIDs = [];

            foreach ($result as $object) {
                $objectID = (int) $object['id'];

                $objectIDs[] = $objectID;

                $objects[$objectID] = [
                    'id' => $objectID,
                    'title' => $object['title'],
                    'type' => (int) $object['type'],
                    'type_title' => $object['type_title'],
                ];
            }

            if (count($categories) > 0) {
                foreach ($categories as $categoryConstant) {
                    $categoryResults = $this->getCMDBCategory()->batchRead($objectIDs, [$categoryConstant]);

                    $count = 0;
                    foreach ($categoryResults as $categoryResult) {
                        $objects[$objectIDs[$count]][$categoryConstant] = $categoryResult;

                        $count++;
                    }
                }
            }

            if (count($result) < $limit) {
                break;
            }

            if ($limit <= 0) {
                break;
            }
        }

        return $objects;
    }

}
