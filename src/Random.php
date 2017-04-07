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

/**
 * Command "random"
 */
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

    protected $rackIDs = [];
    protected $rackID;
    protected $rackRUs;
    protected $rackPos;

    /**
     * Processes some routines before the execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function setup () {
        parent::setup();

        $this->initiateAPI();

        $this->cmdbObjects = new CMDBObjects($this->api);
        $this->cmdbCategory = new CMDBCategory($this->api);

        $this->api->login();

        return $this;
    }

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute() {
        IO::err('Current date and time: %s', date('c', $this->start));

        $worked = false;

        $topics = [
            'countries',
            'subnets',
            'racks',
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

    /**
     * Processes some routines after the execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
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

    protected function createRacks() {
        IO::out('Create racks');

        if (!array_key_exists('amount', $this->config['racks']) ||
            !is_int($this->config['racks']['amount']) ||
            $this->config['racks']['amount'] <= 0) {
            throw new \Exception(
                'Do not know how many racks to create',
                400
            );
        }

        $rackObjects = [];

        for ($i = 0; $i < $this->config['racks']['amount']; $i++) {
            $prefix = null;

            if (array_key_exists('prefix', $this->config['racks'])) {
                $prefix = $this->config['racks']['prefix'];
            }

            $title = $this->genTitle($prefix);

            $rackObjects[] = [
                'title' => $title,
                'type' => $this->config['types']['racks']
            ];
        }

        $rackIDs = $this->createObjects($rackObjects);

        $this->logStat('Created rack objects', count($rackIDs));

        if (array_key_exists('rackUnits', $this->config['racks'])) {
            $amount = $this->config['racks']['rackUnits'];

            if (!is_int($amount) ||
                $amount <= 0) {
                throw new \Exception(
                    'Invalid number of rack units',
                    400
                );
            }

            switch ($amount) {
                case 0:
                    IO::out(
                        'Assign 1 unit to racks'
                    );
                    break;
                default:
                    IO::out(
                        'Assign %s units to racks',
                        $amount
                    );
                    break;
            }

            $this->createCategoryEntries(
                $rackIDs,
                'C__CATG__FORMFACTOR',
                [
                    // Dialog+: 19"
                    'formfactor' => 1,
                    'rackunits' => $amount
                ]
            );
        }

        if (array_key_exists('verticalSlots', $this->config['racks'])) {
            $amount = $this->config['racks']['verticalSlots'];

            if (!is_int($amount) ||
                $amount <= 0) {
                throw new \Exception(
                    'Invalid number of vertical slots',
                    400
                );
            }

            switch ($amount) {
                case 0:
                    IO::out(
                        'Assign 1 vertical slot to racks'
                    );
                    break;
                default:
                    IO::out(
                        'Assign %s vertical slots to racks',
                        $amount
                    );
                    break;
            }

            $this->createCategoryEntries(
                $rackIDs,
                'C__CATS__ENCLOSURE',
                [
                    'vertical_slots_rear' => $amount
                ],
                false
            );
        }

        if (array_key_exists('density', $this->config) &&
            is_array($this->config['density']) &&
            array_key_exists('racksPerRoom', $this->config['density'])) {
            IO::out('Assign racks to rooms');

            if (!is_array($this->config['density']['racksPerRoom']) ||
                !array_key_exists('min', $this->config['density']['racksPerRoom']) ||
                !array_key_exists('max', $this->config['density']['racksPerRoom'])) {
                throw new \Exception(
                    'Missing mininum and maximum amount of racks per room',
                    400
                );
            }

            $min = $this->config['density']['racksPerRoom']['min'];
            $max = $this->config['density']['racksPerRoom']['max'];

            if (!is_int($min) ||
                $min < 1 ||
                !is_int($max) ||
                $max < $min) {
                throw new \Exception(
                    'Invalid minimum and maximum amount of racks per room',
                    400
                );
            }

            $availableRackIDs = $rackIDs;

            $roomObjects = $this->cmdbObjects->readByType(
                $this->config['types']['rooms']
            );

            if (count($roomObjects) === 0) {
                throw new \Exception(
                    'No rooms left'
                );
            }

            foreach ($roomObjects as $roomObject) {
                $roomID = (int) $roomObject['id'];

                $amount = mt_rand($min, $max);

                $attributes = [];

                for ($i = 0; $i < $amount; $i++) {
                    if (count($availableRackIDs) === 0) {
                        IO::out('All racks assigned to rooms');
                        break;
                    }

                    $attributes[] = [
                        'assigned_object' => array_shift(
                            $availableRackIDs
                        )
                    ];
                }

                $this->createMultipleCategoryEntriesPerObject(
                    $roomID,
                    'C__CATG__OBJECT',
                    $attributes
                );

                if (count($availableRackIDs) === 0) {
                    break;
                }
            }

            unset($roomObjects);
        }

        return $this;
    }

    protected function createServers() {
        IO::out('Create servers');

        $this->assertInteger(
            'amount',
            $this->config['servers'],
            'Do not know how many servers to create'
        );

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

        $requests = [];

        if (array_key_exists('ips', $this->config['servers'])) {
            IO::out('Create IP addresses');

            $this->assertInteger(
                'ips',
                $this->config['servers'],
                'Do not how many IP addresses to create per server'
            );

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

        $hostsInRacks = [];

        if (array_key_exists('rackUnits', $this->config['servers'])) {
            IO::out('Mount servers into racks');

            if (!is_array($this->config['servers']['rackUnits']) ||
                !array_key_exists('min', $this->config['servers']['rackUnits']) ||
                !array_key_exists('max', $this->config['servers']['rackUnits'])) {
                throw new \Exception(
                    'Missing mininum and maximum rack units per server',
                    400
                );
            }

            $min = $this->config['servers']['rackUnits']['min'];
            $max = $this->config['servers']['rackUnits']['max'];

            if (!is_int($min) ||
                $min < 1 ||
                !is_int($max) ||
                $max < $min) {
                throw new \Exception(
                    'Invalid minimum and maximum rack units per server',
                    400
                );
            }

            $rackObjects = $this->cmdbObjects->readByType(
                $this->config['types']['racks']
            );

            $this->rackIDs = [];

            foreach ($rackObjects as $rackObject) {
                $this->rackIDs[] = (int) $rackObject['id'];
            }

            if (count($this->rackIDs) === 0) {
                throw new \Exception(
                    'There are no racks'
                );
            }

            foreach ($serverIDs as $serverID) {
                $rackUnits = mt_rand($min, $max);

                $requests[] = [
                    'method' => 'cmdb.category.create',
                    'params' => [
                        'objID' => $serverID,
                        'catgID' => 'C__CATG__FORMFACTOR',
                        'data' => [
                            // Dialog+: 19"
                            'formfactor' => 1,
                            'rackunits' => $rackUnits
                        ]
                    ]
                ];

                $hostsInRacks[$serverID] = $rackUnits;
            }
        }

        if (array_key_exists('model', $this->config['servers'])) {
            IO::out('Add information about models');

            $this->assertArray(
                'model',
                $this->config['servers'],
                'No information about models provided'
            );

            foreach ($serverIDs as $serverID) {
                $index = mt_rand(
                    0,
                    (count($this->config['servers']['model']) - 1)
                );

                $attributes = $this->config['servers']['model'][$index];

                $this->assertString(
                    'manufacturer',
                    $attributes,
                    'Invalid model manufacturer'
                );

                $this->assertString(
                    'title',
                    $attributes,
                    'Invalid model name'
                );

                if (array_key_exists('serial', $attributes)) {
                    $this->assertString(
                        'serial',
                        $attributes,
                        'Invalid serial number'
                    );
                } else {
                    $attributes['serial'] = $this->genTitle() . $this->genTitle();
                }

                $requests[] = [
                    'method' => 'cmdb.category.create',
                    'params' => [
                        'objID' => $serverID,
                        'catgID' => 'C__CATG__MODEL',
                        'data' => $attributes
                    ]
                ];
            }
        }

        if (array_key_exists('cpu', $this->config['servers'])) {
            IO::out('Add information about CPU');

            $this->assertArray(
                'cpu',
                $this->config['servers'],
                'No information about CPU'
            );

            $this->assertInteger(
                'amount',
                $this->config['servers']['cpu'],
                'Invalid amount of CPUs'
            );

            $this->assertString(
                'manufacturer',
                $this->config['servers']['cpu'],
                'Invalid CPU manufacturer'
            );

            $this->assertString(
                'type',
                $this->config['servers']['cpu'],
                'Invalid CPU type'
            );

            $this->assertInteger(
                'cores',
                $this->config['servers']['cpu'],
                'Invalid amount of CPU cores'
            );

            $this->assertString(
                'frequency',
                $this->config['servers']['cpu'],
                'Invalid CPU frequency'
            );

            $attributes = [
                'manufacturer' => $this->config['servers']['cpu']['manufacturer'],
                'type' => $this->config['servers']['cpu']['type'],
                'cores' => $this->config['servers']['cpu']['cores'],
                'frequency' => $this->config['servers']['cpu']['frequency'],
            ];

            if (array_key_exists('title', $this->config['servers']['cpu'])) {
                $this->assertString(
                    'title',
                    $this->config['servers']['cpu'],
                    'Invalid CPU name'
                );

                $attributes['title'] = $this->config['servers']['cpu']['title'];
            }

            foreach ($serverIDs as $serverID) {
                for ($i = 0; $i < $this->config['servers']['cpu']['amount']; $i++) {
                    if (!array_key_exists('title', $attributes)) {
                        $attributes['title'] = '#' . $i;
                    }

                    $requests[] = [
                        'method' => 'cmdb.category.create',
                        'params' => [
                            'objID' => $serverID,
                            'catgID' => 'C__CATG__CPU',
                            'data' => $attributes
                        ]
                    ];
                }
            }
        }

        if (count($requests) > 0) {
            $this->sendBatchRequest($requests);
            $this->logStat('Created category entries', count($requests));
        }

        foreach ($hostsInRacks as $objectID => $neededRUs) {
            $this->assignHostToRack(
                $objectID,
                $neededRUs
            );
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
                throw new \Exception(
                    'Subnet density must be a float between greater than 0 and lower equal 1',
                    400
                );
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

    protected function assignHostToRack($objectID, $neededRUs) {
        if (!isset($this->rackID)) {
            if (count($this->rackIDs) === 0) {
                throw new \Exception(
                    'No space left in racks'
                );
            }

            $this->rackID = array_shift($this->rackIDs);

            $rack = $this->cmdbCategory->readFirst(
                $this->rackID,
                'C__CATG__FORMFACTOR'
            );

            $this->rackRUs = $rack['rackunits'];

            $this->rackPos = 0;

            // We need an empty rack:
            $locallyAssignedObjects = $this->cmdbCategory->read(
                $this->rackID,
                'C__CATG__OBJECT'
            );

            if (count($locallyAssignedObjects) > 0) {
                IO::out(
                    'Rack #%s is not empty',
                    $this->rackID
                );

                $this->rackID = null;

                return $this->assignHostToRack(
                    $objectID,
                    $neededRUs
                );
            }

            IO::out(
                'Use rack #%s',
                $this->rackID
            );
        }

        // @todo This is not the right way to calculate rack unit density:
        if (array_key_exists('density', $this->config) &&
            array_key_exists('rackUnits', $this->config['density'])) {
            if (!is_float($this->config['density']['rackUnits']) ||
                $this->config['density']['rackUnits'] <= 0 ||
                $this->config['density']['rackUnits'] > 1) {
                throw new \Exception(
                    'Rack unit density must be a float between greater than 0 and lower equal 1',
                    400
                );
            }

            $density = round($this->config['density']['rackUnits'] * 100);
            $dice = mt_rand(1, 100);

            if ($density < $dice) {
                $this->rackPos++;
            }
        }

        // No space left:
        if ($this->rackPos >= $this->rackRUs) {
            IO::out(
                'No space left in rack #%s',
                $this->rackID
            );

            $this->rackID = null;

            return $this->assignHostToRack(
                $objectID,
                $neededRUs
            );
        }


        if ($this->rackPos + $neededRUs >= $this->rackRUs) {
            IO::out(
                'Not enough space left in rack #%s',
                $this->rackID
            );

            $this->rackID = null;

            return $this->assignHostToRack(
                $objectID,
                $neededRUs
            );
        }

        $this->createCategoryEntry(
            $objectID,
            'C__CATG__LOCATION',
            [
                'parent' => $this->rackID,
                // Use front and back side:
                'insertion' => 2,
                // Horizontal rack unit:
                'option' => 2,
                'pos' => $this->rackPos
            ]
        );

        $this->rackPos += $neededRUs;

        return $this;
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
            'Create one entry into category "%s" for object #%s',
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
            $categoryConst,
            $count
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
            'Create %s entries into category "%s" for object #%s',
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

    protected function sendBatchRequest($requests) {
        $count = count($requests);

        switch ($count) {
            case 0:
                IO::out('Send 1 sub request in a batch request');
                break;
            default:
                IO::out(
                    'Send %s sub requests in a batch request',
                    $count
                );
                break;
        }

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
                $requests, $index, $length, true
            );

            if ($this->config['limitBatchRequests'] > 0 &&
                $count > $this->config['limitBatchRequests']) {
                if (count($chunk) === 1) {
                    IO::out('Push 1 sub request');
                } else {
                    IO::out('Push %s sub requests', count($chunk));
                }
            }

            $this->api->batchRequest($chunk);

            if ($this->config['limitBatchRequests'] <= 0) {
                break;
            }

            $index += $this->config['limitBatchRequests'];
        }

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

    protected function assertArray($needle, $haystack, $error, $min = 1) {
        if (!array_key_exists($needle, $haystack) ||
            !is_array($haystack[$needle]) ||
            count($haystack[$needle]) < $min) {
            throw new \Exception(
                $error,
                400
            );
        }
    }

    protected function assertString($needle, $haystack, $error) {
        if (!array_key_exists($needle, $haystack) ||
            !is_string($haystack[$needle]) ||
            $haystack[$needle] === '') {
            throw new \Exception(
                $error,
                400
            );
        }
    }

    protected function assertInteger($needle, $haystack, $error, $isPositive = true) {
        if (!array_key_exists($needle, $haystack) ||
            !is_int($haystack[$needle])) {
            throw new \Exception(
                $error,
                400
            );
        }

        if ($isPositive && $haystack[$needle] <= 0) {
            throw new \Exception(
                $error,
                400
            );
        }
    }

}
