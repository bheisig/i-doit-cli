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

use \DirectoryIterator;
use \Exception;
use \RuntimeException;

/**
 * Cache files
 */
class Cache extends Service {

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
     * Cache directory for current i-doit host
     *
     * @var string
     */
    protected $hostDir;

    /**
     * Is cache for current host available?
     *
     * @return bool
     *
     * @throws Exception on error
     */
    public function isCached(): bool {
        $hostDir = $this->getHostDir();

        if (!is_dir($hostDir)) {
            return false;
        }

        $dir = new DirectoryIterator($hostDir);

        foreach ($dir as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }

            if ($file->isFile() === false) {
                continue;
            }

            if ($this->config['cacheLifetime'] > 0 &&
                (time() - $this->config['cacheLifetime'] > $file->getCTime())) {
                return false;
            }

            // First file is valid – this is all we need to know:
            return true;
        }

        return false;
    }

    /**
     * Reads list of object types from cache
     *
     * @return array
     *
     * @throws Exception on error
     */
    public function getObjectTypes(): array {
        $hostDir = $this->getHostDir();
        $filePath = $hostDir . '/object_types';
        return $this->unserializeFromFile($filePath);
    }

    /**
     * Converts an object type title into a object type constant
     *
     * @param string $title Object type title
     *
     * @return string Object type constant, otherwise \Exception is thrown
     *
     * @throws Exception on error
     */
    public function getObjectTypeConstantByTitle(string $title) {
        $objectTypes = $this->getObjectTypes();

        foreach ($objectTypes as $objectType) {
            if (strtolower($objectType['title']) === strtolower($title)) {
                return $objectType['const'];
            }
        }

        throw new RuntimeException(sprintf(
            'Unable to find constant for object type "%s"',
            $title
        ));
    }

    /**
     * Reads information about a category from cache
     *
     * @param string $categoryConst Category constant
     *
     * @return array
     *
     * @throws Exception on error
     */
    public function getCategoryInfo(string $categoryConst): array {
        $hostDir = $this->getHostDir();
        $filePath = $hostDir . '/category__' . $categoryConst;
        return $this->unserializeFromFile($filePath);
    }

    /**
     * Reads list of categories from cache which are assigned to an object type
     *
     * @param string $type Object type constant
     *
     * @return array ['catg' => [['id' => 1, …], ['id' => 1, …]], 'cats' => …]
     *
     * @throws Exception on error
     */
    public function getAssignedCategories(string $type): array {
        $hostDir = $this->getHostDir();
        $filePath = $hostDir . '/object_type__' . $type;
        return $this->unserializeFromFile($filePath);
    }

    /**
     * Reads a list of categories from cache
     *
     * @return array
     *
     * @throws Exception on error
     */
    public function getCategories(): array {
        $categories = [];
        $hostDir = $this->getHostDir();

        $dir = new DirectoryIterator($hostDir);

        foreach ($dir as $file) {
            if ($file->isFile() === false) {
                continue;
            }

            if (strpos($file->getFilename(), 'category__') !== 0) {
                continue;
            }

            $filePath = $hostDir . '/' . $file->getFilename();

            $categories[] = $this->unserializeFromFile($filePath);
        }

        return $categories;
    }

    /**
     * Gets cache directory for current i-doit host
     *
     * @return string
     *
     * @throws Exception when configuration settings are missing
     */
    public function getHostDir(): string {
        if (!array_key_exists('api', $this->config) ||
            !array_key_exists('url', $this->config['api'])) {
            throw new Exception(sprintf(
                'No proper configuration found' . PHP_EOL .
                'Run "%s init" to create configuration settings',
                $this->config['composer']['extra']['name']
            ));
        }

        if (!isset($this->hostDir)) {
            $this->hostDir = $this->config['dataDir'] . '/' .
                sha1($this->config['api']['url']);
        }

        return $this->hostDir;
    }

    /**
     * Read file and create PHP code out of it
     *
     * @param string $filePath Path to file
     *
     * @return mixed
     *
     * @throws RuntimeException on error
     */
    protected function unserializeFromFile(string $filePath) {
        if (!is_readable($filePath)) {
            throw new RuntimeException(sprintf(
                'File "%s" not found or not accessible',
                $filePath
            ));
        }

        $fileContent = file_get_contents($filePath);

        if (!is_string($fileContent)) {
            throw new RuntimeException(sprintf(
                'Unable to read file "%s"',
                $filePath
            ));
        }

        return unserialize($fileContent);
    }

}
