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

/**
 * Trait for API calls
 */
trait APICall {

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
     * @var \bheisig\idoitapi\CMDBObjects
     */
    protected $cmdbObjects;

    /**
     * @var \bheisig\idoitapi\CMDBCategory
     */
    protected $cmdbCategory;

    /**
     * Initiates API
     *
     * @return self Returns itself
     *
     * @throws \Exception when configuration settings are missing
     */
    protected function initiateAPI() {
        if (!array_key_exists('api', $this->config) || !is_array($this->config['api'])) {
            throw new \Exception(
                'No proper configuration: API settings missing.' . PHP_EOL .
                'Run "idoit init" to create configuration settings'
            );
        }

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

        if (array_key_exists('title', $filter) && strpos($filter['title'], '*') !== false) {
            $readFilter = array_filter($filter, function ($key) {
                return $key !== 'title';
            }, \ARRAY_FILTER_USE_KEY);
        } else {
            $readFilter = $filter;
        }

        while (true) {
            if ($limit > 0) {
                $result = $this->cmdbObjects->read($readFilter, $limit, $offset);

                $offset += $limit;
                $counter++;

                if ($counter === 3) {
                    IO::err('This could take a while…');
                }
            } else {
                $result = $this->cmdbObjects->read($readFilter);
            }

            if (count($result) === 0) {
                break;
            }

            if (array_key_exists('title', $filter) && strpos($filter['title'], '*') !== false) {
                $result = array_filter($result, function ($object) use ($filter) {
                    return fnmatch($filter['title'], $object['title']);
                });
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

}