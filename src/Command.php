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

abstract class Command implements Executes {

    protected $config = [];

    /**
     * @var \bheisig\idoitapi\API
     */
    protected $api;

    protected $cacheObjectTypes = [];

    protected $cacheCategories = [];

    protected $cacheAssignedCategories = [];

    protected $hostDir;

    /**
     * UNIX timestamp when execution starts
     *
     * @var int
     */
    protected $start = 0;

    /**
     * Duration in seconds how long execution has taken time
     *
     * @var int
     */
    protected $executionTime = 0;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function setup() {
        $this->start = time();

        return $this;
    }

    public function tearDown() {
        $this->executionTime = time() - $this->start;

        return $this;
    }

    protected function initiateAPI() {
        try {
            $this->api = new API($this->config['api']);
        } catch (\Exception $e) {
            throw new \Exception(
                'No proper configuration: ' . $e->getMessage() . PHP_EOL .
                'Run "idoit init" to create configuration settings'
            );
        }

        return $this;
    }

    protected function getHostDir() {
        if (!array_key_exists('api', $this->config) ||
            !array_key_exists('url', $this->config['api'])) {
            throw new \Exception(
                'No proper configuration found' . PHP_EOL .
                'Run "idoit init" to create configuration settings'
            );
        }

        if (!isset($this->hostDir)) {
            $this->hostDir = $this->config['dataDir'] . '/' .
                sha1($this->config['api']['url']);
        }

        return $this->hostDir;
    }

    protected function isCached() {
        $hostDir = $this->getHostDir();

        if (!is_dir($hostDir)) {
            return false;
        }

        $dir = new \DirectoryIterator($hostDir);

        foreach ($dir as $file) {
            if ($file->isFile()) {
                return true;
            }
        }

        return false;
    }

    protected function isOutdated() {

    }

    protected function getObjectTypes() {
        $hostDir = $this->getHostDir();

        return unserialize(file_get_contents($hostDir . '/object_types'));
    }

    protected function getCategoryInfo($category) {

    }

    protected function getAssignedCategories($type) {

    }

    protected function getCategories() {
        $categories = [];
        $hostDir = $this->getHostDir();

        $dir = new \DirectoryIterator($hostDir);

        foreach ($dir as $file) {
            if ($file->isFile() === false) {
                continue;
            }

            if (strpos($file->getFilename(), 'category__') !== 0) {
                continue;
            }

            $categories[] = unserialize(
                file_get_contents($hostDir . '/' . $file->getFilename())
            );
        }

        return $categories;
    }

    public function showUsage() {
        IO::out('No specific help needed');
    }

}
