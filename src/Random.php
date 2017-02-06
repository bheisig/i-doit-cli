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

use bheisig\idoitapi\CMDBObjects;
use bheisig\idoitapi\CMDBCategory;
use bheisig\idoitapi\Subnet;

class Random extends Command {

    protected $statistics = [];

    /**
     * @var \bheisig\idoitapi\CMDBObjects
     */
    protected $cmdbObjects;

    /**
     * @var \bheisig\idoitapi\CMDBCategory
     */
    protected $cmdbCategory;

    protected $subnetIDs = [];
    protected $subnet;
    protected $subnetID;

    public function setup () {
        parent::setup();

        $this->initiateAPI();

        $this->cmdbObjects = new CMDBObjects($this->api);
        $this->cmdbCategory = new CMDBCategory($this->api);

        $this->api->login();

        return $this;
    }

    public function execute() {
        IO::err('Current date and time: %s', date('c', $this->start));

        $worked = false;

        $topics = [
            'countries',
            'subnets',
            'servers'
        ];

        foreach ($topics as $topic) {
            if (array_key_exists($topic, $this->config)) {
                $method = 'create' . ucfirst($topic);
                $this->$method();

                $worked = true;
            }
        }

        if ($worked === false) {
            throw new \Exception('Nothing to do', 400);
        }

        return $this;
    }

    public function tearDown () {
        parent::tearDown();

        $this->api->logout();

        IO::out('Some statistics:');

        $this->statistics['API calls'] = $this->api->countRequests();
        $this->statistics['Memory peak usage (megabytes)'] = round(
            (memory_get_peak_usage(true) / 1014 / 1024),
            2
        );

        $tabSize = 4;

        $longestText = 0;

        $chars = 0;

        foreach ($this->statistics as $key => $value) {
            if (strlen($key) > $longestText) {
                $longestText = strlen($key);
            }

            if (strlen($value) > $chars) {
                $chars = strlen($value);
            }
        }

        // Because of the ':':
        $longestText++;

        $gap = ($longestText % $tabSize);

        if ($gap !== 0) {
            $gap = $tabSize - $gap;
        }

        $maxLength = $longestText + $gap + $tabSize;

        foreach ($this->statistics as $key => $value) {
            $line = str_repeat(' ', $tabSize);
            $line .= ucfirst($key) . ':';
            $line .= str_repeat(' ', ($maxLength - strlen($key) + 1));
            $line .= str_repeat(' ', ($chars - strlen($value))) . $value;

            IO::out($line);
        }

        IO::out('This took %s seconds.', $this->executionTime);

        IO::out('Done. Have fun :)');

        return $this;
    }

    public function showUsage() {
        IO::out('Roll the dice');
    }

    protected function createCountries() {
        IO::out('Create countries');

        $countryObjects = [];

        foreach ($this->config['countries'] as $country => $attributes) {
            $countryObjects[] = [
                'title' => $country,
                'type' => $this->config['types']['countries']
            ];
        }

        $countryIDs = $this->createObjects($countryObjects);

        unset($countryObjects);

        $this->assignObjectsToLocation($countryIDs, 1);

        $this->logStat('Created country objects', count($countryIDs));

        IO::out('Create cities');

        $countryIndex = 0;

        foreach ($this->config['countries'] as $country => $countrAttributes) {
            if (array_key_exists('cities', $countrAttributes)) {
                $cityObjects = [];

                foreach ($countrAttributes['cities'] as $city => $cityAttributes) {
                    $cityObjects[] = [
                        'title' => $city,
                        'type' => $this->config['types']['cities']
                    ];
                }

                $cityIDs = $this->createObjects($cityObjects);

                unset($cityObjects);

                $this->logStat('Created city objects', count($cityIDs));

                $this->assignObjectsToLocation($cityIDs, $countryIDs[$countryIndex]);

                if (array_key_exists('buildings', $this->config)) {
                    if (array_key_exists('minPerCity', $this->config['buildings']) &&
                        array_key_exists('maxPerCity', $this->config['buildings'])) {
                        IO::out('Create buildings');

                        $buildingIDs = [];

                        foreach ($cityIDs as $cityID) {
                            $amount = mt_rand(
                                $this->config['buildings']['minPerCity'],
                                $this->config['buildings']['maxPerCity']
                            );

                            $buildingObjects = [];

                            for ($i = 0; $i < $amount; $i++) {
                                $buildingObjects[] = [
                                    'title' => $this->genTitle(
                                        $this->config['buildings']['titlePrefix']
                                    ),
                                    'type' => $this->config['types']['buildings']
                                ];
                            }

                            $buildingIDs = $this->createObjects($buildingObjects);

                            unset($buildingObjects);

                            $this->logStat('Created building objects', count($buildingIDs));

                            $this->assignObjectsToLocation($buildingIDs, $cityID);
                        }

                        if (array_key_exists('rooms', $this->config)) {
                            if (array_key_exists('minPerBuilding', $this->config['rooms']) &&
                                array_key_exists('maxPerBuilding', $this->config['rooms'])
                            ) {
                                IO::out('Create rooms');

                                foreach($buildingIDs as $buildingID) {
                                    $amount = mt_rand(
                                        $this->config['rooms']['minPerBuilding'],
                                        $this->config['rooms']['maxPerBuilding']
                                    );

                                    $roomObjects = [];

                                    for ($i = 0; $i < $amount; $i++) {
                                        $roomObjects[] = [
                                            'title' => $this->genTitle(
                                                $this->config['rooms']['titlePrefix']
                                            ),
                                            'type' => $this->config['types']['rooms']
                                        ];
                                    }

                                    $roomIDs = $this->createObjects($roomObjects);

                                    unset($roomObjects);

                                    $this->logStat('Created room objects', count($roomIDs));

                                    $this->assignObjectsToLocation($roomIDs, $buildingID);
                                }
                            }
                        }
                    }
                }
            }

            $countryIndex++;
        }

        return $this;
    }

    protected function createSubnets() {
        IO::out('Create subnets');

        $subnetObjects = [];

        foreach (array_keys($this->config['subnets']) as $subnet) {
            $subnetObjects[] = [
                'title' => $subnet,
                'type' => $this->config['types']['subnets']
            ];
        }

        $subnetIDs = $this->createObjects($subnetObjects);

        unset($subnetObjects);

        $index = 0;

        foreach ($this->config['subnets'] as $attributes) {
            $type = null;

            switch ($attributes['type']) {
                case 'IPv4':
                    $type = 1;
                    break;
                case 'IPv6':
                    $type = 1000;
                    break;
            }

            $this->createCategoryEntry(
                $subnetIDs[$index],
                'C__CATS__NET',
                [
                    'type' => $type,
                    'address' => $attributes['address'],
                    'netmask' => $attributes['mask']
                ],
                false
            );

            $index++;
        }

        $this->logStat('Created subnet objects', count($subnetIDs));

        return $this;
    }

    protected function createServers() {
        IO::out('Create servers');

        if (!is_array($this->config['servers']) ||
            count($this->config['servers']) === 0) {
            throw new \Exception('Missing configuration settings for servers', 400);
        }

        if (!array_key_exists('amount', $this->config['servers']) ||
            !is_int($this->config['servers']['amount']) ||
            $this->config['servers']['amount'] <= 0) {
            throw new \Exception('Do not know how many servers to create', 400);
        }

        $serverObjects = [];

        for ($i = 0; $i < $this->config['servers']['amount']; $i++) {
            $prefix = null;

            if (array_key_exists('prefix', $this->config['servers'])) {
                $prefix = $this->config['servers']['prefix'];
            }

            $title = $this->genTitle($prefix);

            $serverObjects[] = [
                'title' => $title,
                'type' => $this->config['types']['servers']
            ];
        }

        $serverIDs = $this->createObjects($serverObjects);

        $this->logStat('Created server objects', count($serverIDs));

        if (array_key_exists('ips', $this->config['servers'])) {
            IO::out('Create IP addresses');

            if (!is_int($this->config['servers']['ips']) ||
                $this->config['servers']['ips'] <= 0) {
                throw new \Exception(
                    'Do not how many IP addresses to create per server',
                    400
                );
            }

            $subnetObjects = $this->cmdbObjects->readByType(
                $this->config['types']['subnets']
            );

            $this->subnetIDs = [];

            foreach ($subnetObjects as $subnetObject) {
                // Ignore "global" subnets:
                if (in_array($subnetObject['title'], ['Global v4', 'Global v6'])) {
                    continue;
                }

                $this->subnetIDs[] = (int) $subnetObject['id'];
            }

            unset($subnetObjects);

            if (count($this->subnetIDs) === 0) {
                throw new \Exception(
                    'There are no proper subnets'
                );
            }

            $index = 0;

            foreach ($serverIDs as $serverID) {
                $attributes = [];

                for ($i = 0; $i < $this->config['servers']['ips']; $i++) {
                    $nextIP = $this->nextIP();

                    $attributes[] = [
                        'active' => 1,
                        'primary' => 1,
                        'type' => 1, // "IPv4 (Internet Protocol v4)"
                        'net' => $this->subnetID,
                        'ipv4_assignment' => 2, // "static"
                        'ipv4_address' => $nextIP,
                        'hostname' => $serverObjects[$index]['title']
                    ];
                }

                $this->createMultipleCategoryEntriesPerObject(
                    $serverID,
                    'C__CATG__IP',
                    $attributes
                );

                $index++;
            }

        }

        return $this;
    }

    protected function nextIP() {
        if (!isset($this->subnet)) {
            if (count($this->subnetIDs) === 0) {
                throw new \Exception('No IP addresses left', 400);
            }
            $this->subnetID = array_shift($this->subnetIDs);
            $this->subnet = new Subnet($this->api);
            $this->subnet->load($this->subnetID);
        }

        if ($this->subnet->hasNext() === false) {
            $this->subnet = null;
            return $this->nextIP();
        }

        $next = $nextIP = $this->subnet->next();

        if (array_key_exists('density', $this->config) &&
            is_array($this->config['density']) &&
            array_key_exists('subnets', $this->config['density'])) {
            if (!is_float($this->config['density']['subnets']) ||
                $this->config['density']['subnets'] <= 0 ||
                $this->config['density']['subnets'] > 1) {
                throw new \Exception('Subnet density must be a float between greater than 0 and lower equal 1');
            }

            $density = round($this->config['density']['subnets'] * 100);
            $dice = mt_rand(1, 100);

            if ($density < $dice) {
                if ($this->subnet->hasNext() === false) {
                    $this->subnet = null;
                    return $this->nextIP();
                }

                return $this->subnet->next();
            }
        }

        return $next;
    }

    protected function createObjects($objects) {
        $count = count($objects);

        if ($this->config['limitBatchRequests'] > 0 &&
            $count > $this->config['limitBatchRequests']) {
            IO::out(
                'Batch requests are limited to %s sub requests',
                $this->config['limitBatchRequests']
            );
        }

        $objectIDs = [];

        $index = 0;

        while ($index < $count) {
            $length = null;

            if ($this->config['limitBatchRequests'] > 0) {
                $length = $this->config['limitBatchRequests'];
            }

            $chunk = array_slice(
                $objects, $index, $length, true
            );

            if ($this->config['limitBatchRequests'] > 0 &&
                $count > $this->config['limitBatchRequests']) {
                if (count($chunk) === 1) {
                    IO::out('Create 1 object');
                } else {
                    IO::out('Create %s objects', count($chunk));
                }
            }

            $objectIDs = array_merge(
                $objectIDs,
                $this->cmdbObjects->create($chunk)
            );

            if ($this->config['limitBatchRequests'] <= 0) {
                break;
            }

            $index += $this->config['limitBatchRequests'];
        }

        return $objectIDs;
    }

    protected function assignObjectsToLocation($objectIDs, $locationID) {
        return $this->createCategoryEntries(
            $objectIDs,
            'C__CATG__LOCATION',
            [
                'parent' => $locationID
            ]
        );
    }

    protected function createCategoryEntry($objectID, $categoryConst, $attributes, $isGlobal = true) {
        IO::out(
            'Create one entry into category "%s" for object %s',
            $categoryConst,
            $objectID
        );

        $this->cmdbCategory->create($objectID, $categoryConst, $attributes, $isGlobal);

        $this->logStat('Created category entries', 1);

        return $this;
    }

    protected function createCategoryEntries($objectIDs, $categoryConst, $attributes, $isGlobal = true) {
        $count = count($objectIDs);

        IO::out(
            'Create same entry into category "%s" for %s objects',
            $count,
            $categoryConst
        );

        if ($this->config['limitBatchRequests'] > 0 &&
            $count > $this->config['limitBatchRequests']) {
            IO::out(
                'Batch requests are limited to %s sub requests',
                $this->config['limitBatchRequests']
            );
        }

        $index = 0;

        while ($index < $count) {
            $length = null;

            if ($this->config['limitBatchRequests'] > 0) {
                $length = $this->config['limitBatchRequests'];
            }

            $chunk = array_slice(
                $objectIDs, $index, $length, true
            );

            if ($this->config['limitBatchRequests'] > 0 &&
                $count > $this->config['limitBatchRequests']) {
                if (count($chunk) === 1) {
                    IO::out('Push 1 sub request');
                } else {
                    IO::out('Push %s sub requests', count($chunk));
                }
            }

            $this->cmdbCategory->batchCreate(
                $chunk,
                $categoryConst,
                [$attributes],
                $isGlobal
            );

            if ($this->config['limitBatchRequests'] <= 0) {
                break;
            }

            $index += $this->config['limitBatchRequests'];
        }

        $this->logStat('Created category entries', $count);

        return $this;
    }

    protected function createMultipleCategoryEntriesPerObject($objectID, $categoryConst, $attributes, $isGlobal = true) {
        $count = count($attributes);

        IO::out(
            'Create %s entries into category "%s" for object %s',
            $count,
            $categoryConst,
            $objectID
        );

        if ($this->config['limitBatchRequests'] > 0 &&
            $count > $this->config['limitBatchRequests']) {
            IO::out(
                'Batch requests are limited to %s sub requests',
                $this->config['limitBatchRequests']
            );
        }

        $index = 0;

        while ($index < $count) {
            $length = null;

            if ($this->config['limitBatchRequests'] > 0) {
                $length = $this->config['limitBatchRequests'];
            }

            $chunk = array_slice(
                $attributes, $index, $length, true
            );

            if ($this->config['limitBatchRequests'] > 0 &&
                $count > $this->config['limitBatchRequests']) {
                if (count($chunk) === 1) {
                    IO::out('Push 1 sub request');
                } else {
                    IO::out('Push %s sub requests', count($chunk));
                }
            }

            $this->cmdbCategory->batchCreate(
                [$objectID],
                $categoryConst,
                $chunk,
                $isGlobal
            );

            if ($this->config['limitBatchRequests'] <= 0) {
                break;
            }

            $index += $this->config['limitBatchRequests'];
        }

        $this->logStat('Created category entries', $count);

        return $this;
    }

    protected function logStat($key, $value) {
        if (!array_key_exists($key, $this->statistics)) {
            $this->statistics[$key] = $value;
        } else {
            $this->statistics[$key] += $value;
        }
    }

    protected function genTitle($prefix = null, $suffix = null) {
        $title = '';

        if (isset($prefix)) {
            $title = $prefix;
        }

        $title .= substr(sha1(microtime()), 0, 5);

        if (isset($suffix)) {
            $title .= $suffix;
        }

        return $title;
    }

}
