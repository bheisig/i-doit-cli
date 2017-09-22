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

/**
 * Trait for API calls
 */
trait Cache {

    /**
     * Configuration settings
     *
     * @var array Associative array
     */
    protected $config = [];

    /**
     * Cached object types
     *
     * @var array Indexed array of associative arrays
     */
    protected $cacheObjectTypes = [];

    /**
     * Cached categories
     *
     * @var array Indexed array of associative arrays
     */
    protected $cacheCategories = [];

    /**
     * Cached assignments between categories and object types
     *
     * @var array Indexed array of associative arrays
     */
    protected $cacheAssignedCategories = [];

    /**
     * Is cache for current host available?
     *
     * @return bool
     *
     * @throws \Exception on error
     */
    protected function isCached() {
        $hostDir = $this->getHostDir();

        if (!is_dir($hostDir)) {
            return false;
        }

        $dir = new \DirectoryIterator($hostDir);

        foreach ($dir as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }

            if ($file->isFile() === false) {
                return false;
            }

            if ($this->config['cacheLifetime'] > 0 &&
                (time() - $this->config['cacheLifetime'] > $file->getCTime())) {
                IO::err(
                    'Your cache is out-dated. Please re-run "%s init".',
                    $this->config['basename']
                );
                IO::err('');
            }

            // First file is valid – this is all I need to know:
            return true;
        }

        return false;
    }

    /**
     * Reads list of object types from cache
     *
     * @return array
     *
     * @throws \Exception on error
     */
    protected function getObjectTypes() {
        $hostDir = $this->getHostDir();

        return unserialize(file_get_contents($hostDir . '/object_types'));
    }

    /**
     * Converts an object type title into a object type constant
     *
     * @param string $title Object type title
     *
     * @return string Object type constant, otherwise NULL on error
     */
    protected function getObjectTypeConstantByTitle($title) {
        $objectTypes = $this->getObjectTypes();

        foreach ($objectTypes as $objectType) {
            if (strtolower($objectType['title']) === strtolower($title)) {
                return $objectType['const'];
            }
        }

        return null;
    }

    /**
     * Reads information about a category from cache
     *
     * @param string $categoryConst Category constant
     *
     * @return array
     *
     * @throws \Exception on error
     */
    protected function getCategoryInfo($categoryConst) {
        $hostDir = $this->getHostDir();

        return unserialize(file_get_contents($hostDir . '/category__' . $categoryConst));
    }

    /**
     * Reads list of categories from cache which are assigned to an object type
     *
     * @param string $type Object type constant
     *
     * @return array ['catg' => [['id' => 1, …], ['id' => 1, …]], 'cats' => …]
     *
     * @throws \Exception on error
     */
    protected function getAssignedCategories($type) {
        $hostDir = $this->getHostDir();

        return unserialize(file_get_contents($hostDir . '/object_type__' . $type));
    }

    /**
     * Reads a list of categories from cache
     *
     * @return array
     *
     * @throws \Exception on error
     */
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

    /**
     * Gets cache directory for current i-doit host
     *
     * @return string
     *
     * @throws \Exception when configuration settings are missing
     */
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

}