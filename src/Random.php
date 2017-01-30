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

        if (array_key_exists('countries', $this->config)) {
            $this->createCountries();

            $worked = true;
        }

        if ($worked === false) {
            throw new \Exception('Nothing to do', 400);
        }

        return $this;
    }

    public function tearDown () {
        parent::tearDown();

        $this->api->logout();

        $this->statistics['API calls: '] = $this->api->countRequests();

        if (count($this->statistics) > 0) {
            IO::out('Some statistics:');

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
                                $hash = sha1(microtime());
                                $title = $this->config['buildings']['titlePrefix'] .
                                    substr($hash, 0, 5);

                                $buildingObjects[] = [
                                    'title' => $title,
                                    'type' => $this->config['types']['buildings']
                                ];
                            }

                            $buildingIDs = $this->createObjects($buildingObjects);

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
                                        $hash = sha1(microtime());
                                        $title = $this->config['rooms']['titlePrefix'] .
                                            substr($hash, 0, 5);

                                        $roomObjects[] = [
                                            'title' => $title,
                                            'type' => $this->config['types']['rooms']
                                        ];
                                    }

                                    $roomIDs = $this->createObjects($roomObjects);

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
        $count = count($objectIDs);

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

            $this->cmdbCategory->batchCreate(
                $chunk,
                'C__CATG__LOCATION',
                [[
                    'parent' => $locationID
                ]]
            );

            if ($this->config['limitBatchRequests'] <= 0) {
                break;
            }

            $index += $this->config['limitBatchRequests'];
        }

        $this->logStat('Created category entries', count($objectIDs));
    }

    protected function logStat($key, $value) {
        if (!array_key_exists($key, $this->statistics)) {
            $this->statistics[$key] = $value;
        } else {
            $this->statistics[$key] += $value;
        }
    }

}
