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

namespace bheisig\idoitcli\Service;

use \Exception;
use \RuntimeException;
use bheisig\cli\Log;
use bheisig\idoitapi\API;
use bheisig\idoitapi\Request;
use bheisig\idoitapi\CMDBCategory;
use bheisig\idoitapi\CMDBCategoryInfo;
use bheisig\idoitapi\CMDBDialog;
use \bheisig\idoitapi\CMDBLogbook;
use bheisig\idoitapi\CMDBObject;
use bheisig\idoitapi\CMDBObjects;
use bheisig\idoitapi\CMDBObjectTypeCategories;
use bheisig\idoitapi\CMDBObjectTypes;
use bheisig\idoitapi\Idoit;
use bheisig\idoitapi\Subnet;

/**
 * i-doit API factory
 */
class IdoitAPIFactory extends Service {

    /**
     * API
     *
     * @var API
     */
    protected $api;

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
     * @throws Exception when configuration settings are missing
     */
    public function __construct(array $config, Log $log) {
        parent::__construct($config, $log);

        try {
            $this->api = new API($this->config['api']);
            $this->api->login();
        } catch (Exception $e) {
            throw new Exception(
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
            if (is_a($class, Request::class, true) === false) {
                throw new RuntimeException(sprintf(
                    '%s is not an API request class',
                    $class
                ));
            }

            $this->instances[$class] = new $class($this->api);
        }

        return $this->instances[$class];
    }

    /**
     * Factory for CMDB
     *
     * @return Idoit
     */
    public function getCMDB(): Idoit {
        return $this->getInstanceOf(Idoit::class);
    }

    /**
     * Factory for CMDBObject
     *
     * @return CMDBObject
     */
    public function getCMDBObject(): CMDBObject {
        return $this->getInstanceOf(CMDBObject::class);
    }

    /**
     * Factory for CMDBObjects
     *
     * @return CMDBObjects
     */
    public function getCMDBObjects(): CMDBObjects {
        return $this->getInstanceOf(CMDBObjects::class);
    }

    /**
     * Factory for CMDBObjectTypeCategories
     *
     * @return CMDBObjectTypeCategories
     */
    public function getCMDBObjectTypeCategories(): CMDBObjectTypeCategories {
        return $this->getInstanceOf(CMDBObjectTypeCategories::class);
    }

    /**
     * Factory for CMDBObjectTypes
     *
     * @return CMDBObjectTypes
     */
    public function getCMDBObjectTypes(): CMDBObjectTypes {
        return $this->getInstanceOf(CMDBObjectTypes::class);
    }

    /**
     * Factory for CMDBCategory
     *
     * @return CMDBCategory
     */
    public function getCMDBCategory(): CMDBCategory {
        return $this->getInstanceOf(CMDBCategory::class);
    }

    /**
     * Factory for CMDBCategoryInfo
     *
     * @return CMDBCategoryInfo
     */
    public function getCMDBCategoryInfo(): CMDBCategoryInfo {
        return $this->getInstanceOf(CMDBCategoryInfo::class);
    }

    /**
     * Factory for CMDBDialog
     *
     * @return CMDBDialog
     */
    public function getCMDBDialog(): CMDBDialog {
        return $this->getInstanceOf(CMDBDialog::class);
    }

    /**
     * Factory for CMDBDialog
     *
     * @return CMDBLogbook
     */
    public function getCMDBLogbook(): CMDBLogbook {
        return $this->getInstanceOf(CMDBLogbook::class);
    }

    /**
     * Factory for Subnet
     *
     * @return Subnet
     */
    public function getSubnet(): Subnet {
        return $this->getInstanceOf(Subnet::class);
    }

}
