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
use bheisig\idoitapi\CMDBObjectTypes;
use bheisig\idoitapi\CMDBObjectTypeCategories;
use bheisig\idoitapi\CMDBCategoryInfo;

class Init extends Command {

    public function execute() {
        try {
            $this
                ->createBaseDir()
                ->createUserConfig()
                ->clearCache()
                ->createCache();

            IO::out('Done.');
        } catch (\Exception $e) {
            $code = $e->getCode();

            if ($code === 0) {
                $code = 500;
            }

            throw new \Exception(
                'Unable to initiate idoitcli: ' .
                    $e->getMessage(),
                    $code
            );
        }

        return $this;
    }

    /**
     * @param $file
     * @param $value
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function serialize($file, $value) {
        $filePath = $this->config['dataDir'] . '/' . $file;

        $status = file_put_contents($filePath, serialize($value));

        if ($status === false) {
            throw new \Exception(sprintf(
                'Unable to write to cache file "%s"',
                $filePath
            ));
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function createBaseDir() {
        if (!is_dir($this->config['baseDir'])) {
            IO::out('Create base directory');

            $status = mkdir($this->config['baseDir'], 0775, true);

            if ($status === false) {
                throw new \Exception(sprintf(
                    'Unable to create base directory "%s"',
                    $this->config['baseDir']
                ));
            }
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function createUserConfig() {
        if (file_exists($this->config['userConfig'])) {
            $answer = strtolower(IO::in(
                'Do you like to re-configure your settings in file "%s"? [Y]es, [n]o:',
                $this->config['userConfig']
            ));

            switch ($answer) {
                case 'yes':
                case 'y':
                case '':
                    break;
                case 'no':
                case 'n':
                    return $this;
                default:
                    throw new \Exception('Do not know what to do.');
            }
        }

        IO::out('Please answer some questions:');

        $url = IO::in(
            'What is the URL to your i-doit installation? (For example: https://cmdb.example.com/i-doit/)'
        );

        if (strpos($url, 'https://') === 0) {
            $suggestedPort = 443;
        } else if (strpos($url, 'http://') === 0) {
            $suggestedPort = 80;
        } else {
            throw new \Exception('Your entered URL does not contain a valid protocol like "https://" or "http://"');
        }

        $url .= ((substr($url, -1) !== '/') ? '/' : '') . 'src/jsonrpc.php';

        $answer = IO::in(
            'Which port is the Web server behind i-doit using? [%s]',
            $suggestedPort
        );

        $port = null;

        if (empty($answer)) {
            $port = $suggestedPort;
        } else if (!is_numeric($answer) || (int) $answer <= 0 || (int) $answer > 65535) {
            throw new \Exception('Invalid port number');
        } else {
            $port = (int) $answer;
        }

        $key = IO::in(
            'What is the API key you configured in your i-doit installation?'
        );

        if (empty($key)) {
            throw new \Exception('Without an API key no connection to i-doit could be established.');
        }

        $config = [
            'api' => [
                'url' => $url,
                'port' => $port,
                'key' => $key
            ]
        ];

        $username = IO::in(
            'What is your username? (optional)'
        );

        if (!empty($username)) {
            $password = IO::in(
                'What is your password?'
            );

            if (empty($password)) {
                throw new \Exception('Your username needs a password');
            }

            $config['api']['username'] = $username;
            $config['api']['password'] = $password;
        }

        IO::out('Write settings into configuration file "%s"', $this->config['userConfig']);

        $status = file_put_contents(
            $this->config['userConfig'],
            json_encode($config, JSON_PRETTY_PRINT) . PHP_EOL
        );

        if ($status === false) {
            throw new \Exception(sprintf(
                'Unable to create configuration file "%s"',
                $this->config['userConfig']
            ));
        }

        $this->config = array_merge_recursive_overwrite($this->config, $config);

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function clearCache() {
        if (!is_dir($this->config['dataDir'])) {
            IO::out('Create cache directory');

            $status = mkdir($this->config['dataDir'], 0775, true);

            if ($status === false) {
                throw new \Exception(sprintf(
                    'Unable to create cache directory "%s"',
                    $this->config['dataDir']
                ));
            }
        } else {
            IO::out('Clear cache files');

            $files = new \DirectoryIterator($this->config['dataDir']);

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $status = @unlink($file->getPathname());

                    if ($status === false) {
                        throw new \Exception(sprintf(
                            'Unable to clear cache in "%s". Unable to delete file "%s"',
                            $this->config['dataDir'],
                            $file->getPathname()
                        ));
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function createCache() {
        $this->initiateAPI();

        $this->api->login();

        IO::out('Fetch list of object types');

        $cmdbObjectTypes = new CMDBObjectTypes($this->api);
        $objectTypes = $cmdbObjectTypes->read();

        $this->serialize(
            'object_types',
            $objectTypes
        );

        IO::out('Fetch list of assigned categories');

        $objectTypeIDs = [];

        foreach ($objectTypes as $objectType) {
            $objectTypeIDs[] = (int) $objectType['id'];
        }

        $cmdbAssignedCategories = new CMDBObjectTypeCategories($this->api);
        $batchedAssignedCategories = $cmdbAssignedCategories->batchReadByID($objectTypeIDs);

        for ($i = 0; $i < count($objectTypes); $i++) {
            $this->serialize(
                sprintf(
                    'object_type__%s',
                    $objectTypes[$i]['const']
                ),
                $batchedAssignedCategories[$i]
            );
        }

        IO::out('Fetch information about categories');

        $consts = [];

        $categories = [];

        foreach ($batchedAssignedCategories as $assignedCategories) {
            foreach ($assignedCategories as $type => $categoryList) {
                if ($type === 'catg') {
                    $isGlobal = true;
                } else if ($type === 'cats') {
                    $isGlobal = false;
                } else {
                    IO::err('Ignore customized categories');
                    continue;
                }

                foreach ($categoryList as $category) {
                    $consts[$category['const']] = $isGlobal;
                    $categories[$category['const']] = $category;
                }
            }
        }

        if (count($consts) > 0) {
            $cmdbCategoryInfo = new CMDBCategoryInfo($this->api);

            $batchCategoryInfo = $cmdbCategoryInfo->batchRead($consts);

            $categoryConsts = array_keys($consts);

            $counter = 0;

            foreach ($categoryConsts as $categoryConst) {
                $categories[$categoryConst]['properties'] = $batchCategoryInfo[$counter];

                $this->serialize(
                    sprintf(
                        'category__%s',
                        $categoryConst
                    ),
                    $categories[$categoryConst]
                );

                $counter++;
            }
        }

        $this->api->logout();

        return $this;
    }

}
