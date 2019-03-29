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

namespace bheisig\idoitcli\Command;

use \Exception;
use \BadMethodCallException;
use bheisig\idoitapi\Subnet;

/**
 * Command "random"
 */
class Random extends Command {

    const WARN_AT_HIGH_NUMBER_OF_REQUESTS = 1000;

    protected $statistics = [];

    protected $subnetIDs = [];
    protected $subnet;
    protected $subnetID;

    protected $rackIDs = [];
    protected $rackID;
    protected $rackRUs;
    protected $rackPos;

    protected $laptopIDs = [];
    protected $applicationIDs = [];
    protected $licenseIDs = [];
    protected $licenseKeyIDs = [];

    /**
     * Execute command
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    public function execute(): self {
        $this->log
            ->printAsMessage()
            ->info($this->getDescription())
            ->printEmptyLine();

        $worked = false;

        $topics = [
            'countries',
            'subnets',
            'racks',
            'servers',
            'persons',
            'laptops',
            'applications',
            'licenses'
        ];

        foreach ($topics as $topic) {
            if (array_key_exists($topic, $this->config)) {
                $method = 'create' . ucfirst($topic);
                $this->$method();

                $worked = true;
            }
        }

        $tasks = [
            'installApplications'
        ];

        foreach ($tasks as $task) {
            if (array_key_exists($task, $this->config)) {
                $this->$task();

                $worked = true;
            }
        }

        if ($worked === false) {
            throw new Exception('Nothing to do', 400);
        }

        return $this;
    }

    /**
     * Processes some routines after the execution
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    public function tearDown(): self {
        $this->log->debug('Some statistics:');

        $this->statistics['API calls'] = $this->useIdoitAPIFactory()->getAPI()->countRequests();

        $tabSize = 4;

        $longestText = 0;

        $chars = 0;

        foreach ($this->statistics as $key => $value) {
            $value = (string) $value;

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
            $value = (string) $value;

            $line = str_repeat(' ', $tabSize);
            $line .= ucfirst($key) . ':';
            $line .= str_repeat(' ', ($maxLength - strlen($key) + 1));
            $line .= str_repeat(' ', ($chars - strlen($value))) . $value;

            $this->log->debug($line);
        }

        parent::tearDown();

        return $this;
    }

    /**
     * Create countries from list
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createCountries(): self {
        $this->log->info('Create countries');

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

        $this->log->info('Create cities');

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
                        $this->log->info('Create buildings');

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
                                $this->log->info('Create rooms');

                                foreach ($buildingIDs as $buildingID) {
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

    /**
     * Create subnets from list
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createSubnets(): self {
        $this->log->info('Create subnets');

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
                ]
            );

            $index++;
        }

        $this->logStat('Created subnet objects', count($subnetIDs));

        return $this;
    }

    /**
     * Create random racks
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createRacks(): self {
        $this->log->info('Create racks');

        if (!array_key_exists('amount', $this->config['racks']) ||
            !is_int($this->config['racks']['amount']) ||
            $this->config['racks']['amount'] <= 0) {
            throw new Exception(
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
                throw new Exception(
                    'Invalid number of rack units',
                    400
                );
            }

            switch ($amount) {
                case 0:
                    $this->log->info(
                        'Assign 1 unit to racks'
                    );
                    break;
                default:
                    $this->log->info(
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
                throw new Exception(
                    'Invalid number of vertical slots',
                    400
                );
            }

            switch ($amount) {
                case 0:
                    $this->log->info(
                        'Assign 1 vertical slot to racks'
                    );
                    break;
                default:
                    $this->log->info(
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
                ]
            );
        }

        if (array_key_exists('density', $this->config) &&
            is_array($this->config['density']) &&
            array_key_exists('racksPerRoom', $this->config['density'])) {
            $this->log->info('Assign racks to rooms');

            if (!is_array($this->config['density']['racksPerRoom']) ||
                !array_key_exists('min', $this->config['density']['racksPerRoom']) ||
                !array_key_exists('max', $this->config['density']['racksPerRoom'])) {
                throw new Exception(
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
                throw new Exception(
                    'Invalid minimum and maximum amount of racks per room',
                    400
                );
            }

            $availableRackIDs = $rackIDs;

            $roomObjects = $this->useIdoitAPIFactory()->getCMDBObjects()->readByType(
                $this->config['types']['rooms']
            );

            if (count($roomObjects) === 0) {
                throw new Exception(
                    'No rooms left'
                );
            }

            foreach ($roomObjects as $roomObject) {
                $roomID = (int) $roomObject['id'];

                $amount = mt_rand($min, $max);

                $attributes = [];

                for ($i = 0; $i < $amount; $i++) {
                    if (count($availableRackIDs) === 0) {
                        $this->log->info('All racks assigned to rooms');
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

    /**
     * Create random hosts
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createServers(): self {
        $this->log->info('Create servers');

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

        $this->logStat('Created %s server objects', count($serverIDs));

        $requests = [];

        if (array_key_exists('ips', $this->config['servers'])) {
            $this->log->info('Create IP addresses');

            $this->assertInteger(
                'ips',
                $this->config['servers'],
                'Do not how many IP addresses to create per server'
            );

            $subnetObjects = $this->useIdoitAPIFactory()->getCMDBObjects()->readByType(
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
                throw new Exception(
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
            $this->log->info('Mount servers into racks');

            if (!is_array($this->config['servers']['rackUnits']) ||
                !array_key_exists('min', $this->config['servers']['rackUnits']) ||
                !array_key_exists('max', $this->config['servers']['rackUnits'])) {
                throw new Exception(
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
                throw new Exception(
                    'Invalid minimum and maximum rack units per server',
                    400
                );
            }

            $rackObjects = $this->useIdoitAPIFactory()->getCMDBObjects()->readByType(
                $this->config['types']['racks']
            );

            $this->rackIDs = [];

            foreach ($rackObjects as $rackObject) {
                $this->rackIDs[] = (int) $rackObject['id'];
            }

            if (count($this->rackIDs) === 0) {
                throw new Exception(
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
            $this->log->info('Add information about models');

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
            $this->log->info('Add information about CPU');

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

            $this->assertFloat(
                'frequency',
                $this->config['servers']['cpu'],
                'Invalid CPU frequency (in GHz)'
            );

            $attributes = [
                'manufacturer' => $this->config['servers']['cpu']['manufacturer'],
                'type' => $this->config['servers']['cpu']['type'],
                'cores' => $this->config['servers']['cpu']['cores'],
                'frequency' => $this->config['servers']['cpu']['frequency'],
                'frequency_unit' => 3 // GHz
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

    /**
     * Create persons with random names, e-mail addresses and desks
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createPersons(): self {
        $this->assertInteger(
            'amount',
            $this->config['persons'],
            'Do not know how many persons to create'
        );

        $amount = $this->config['persons']['amount'];

        switch ($amount) {
            case 1:
                $this->log->info('Create 1 person');
                break;
            default:
                $this->log->info('Create %s persons', $amount);
                break;
        }

        $persons = [];

        for ($i = 0; $i < $amount; $i++) {
            $forenameData = $this->pickRandomElement($this->config['persons']['forenames']);
            $forename = $forenameData['name'];
            $sex = $forenameData['sex'];
            $surname = $this->pickRandomElement($this->config['persons']['surnames']);
            $name = sprintf('%s %s', $forename, $surname);

            foreach ($persons as $person) {
                if ($person['title'] === $name) {
                    $this->log->debug('Name collision detected; re-try…');
                    $i--;
                    continue 2;
                }
            }

            $persons[] = [
                'title' => $name,
                'type' => $this->config['types']['persons'],
                'sex' => $sex
            ];
        }

        if (count($persons) === 0) {
            $this->log->notice('No persons to create');
            return $this;
        }

        $personIDs = $this->createObjects($persons);

        $this->log->info('Add personal information…');

        $requests = [];

        foreach ($personIDs as $index => $personID) {
            $person = $persons[$index];

            $email = sprintf(
                '%s@%s',
                strtolower(str_replace(' ', '.', $person['title'])),
                $this->config['persons']['emailDomain']
            );

            $phone = sprintf(
                '%s%s',
                $this->config['persons']['phonePrefix'],
                $personID
            );

            $requests[] = [
                'method' => 'cmdb.category.create',
                'params' => [
                    'objID' => $personID,
                    'category' => 'C__CATS__PERSON',
                    'data' => [
                        'mail' => $email,
                        'salutation' => $person['sex'],
                        'phone_company' => $phone
                    ]
                ]
            ];

            $this->log->debug(
                '    %s. %s [%s], %s, %s',
                ($person['sex'] === 'f') ? 'Mrs' : 'Mr',
                $person['title'],
                $personID,
                $email,
                $phone
            );
        }

        if (count($requests) > 0) {
            $this->sendBatchRequest($requests);
        }

        if ($this->config['persons']['createDesks'] === false) {
            return $this;
        }

        switch ($amount) {
            case 1:
                $this->log->info('Create 1 desk…');
                break;
            default:
                $this->log->info('Create %s desks…', $amount);
                break;
        }

        $desks = [];

        foreach ($personIDs as $index => $personID) {
            $person = $persons[$index];

            $desks[] = [
                'title' => $person['title'],
                'type' => $this->config['types']['desks']
            ];
        }

        $deskIDs = $this->createObjects($desks);

        $this->log->info('Assign each person to its desk…');

        $requests = [];

        foreach ($personIDs as $index => $personID) {
            $requests[] = [
                'method' => 'cmdb.category.create',
                'params' => [
                    'objID' => $personID,
                    'category' => 'C__CATG__PERSON_ASSIGNED_WORKSTATION',
                    'data' => [
                        'assigned_workstations' => $deskIDs[$index]
                    ]
                ]
            ];
        }

        if (count($requests) > 0) {
            $this->sendBatchRequest($requests);
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createLaptops(): self {
        $this->laptopIDs = $this->generateObjects('laptops');

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createApplications(): self {
        $this->applicationIDs = $this->generateObjects('applications');

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createLicenses(): self {
        $this->licenseIDs = $this->generateObjects('licenses');

        $this->log->info('…with keys');

        $requests = [];

        foreach ($this->licenseIDs as $licenseID) {
            $requests[] = [
                'method' => 'cmdb.category.create',
                'params' => [
                    'objID' => $licenseID,
                    'category' => 'C__CATS__LICENCE_LIST',
                    'data' => [
                        'key' => $this->genTitle(),
                        'type' => 2, // Volume
                        'amount' => 1000
                    ]
                ]
            ];
        }

        $results = $this->processRequests($requests);

        $index = 0;

        foreach ($results as $result) {
            $licenseID = $this->licenseIDs[$index];
            $this->licenseKeyIDs[$licenseID] = (int) $result['id'];
            $index++;
        }

        return $this;
    }

    /**
     * @throws Exception on error
     * @return self Returns itself
     */
    protected function installApplications(): self {
        if (count($this->laptopIDs) === 0) {
            throw new BadMethodCallException('There are no laptops');
        }

        $requiredSettings = [
            'min',
            'max',
            'addLicense'
        ];

        foreach ($requiredSettings as $requiredSetting) {
            if (!array_key_exists($requiredSetting, $this->config['installApplications'])) {
                throw new BadMethodCallException(sprintf(
                    'Setting "installApplications.%s" missing',
                    $requiredSetting
                ));
            }
        }

        switch (count($this->applicationIDs)) {
            case 0:
                throw new BadMethodCallException('There are no applications');
            case 1:
                $this->log->info('Install 1 application…');
                break;
            default:
                $this->log->info('Install %s applications…', count($this->applicationIDs));
                break;
        }

        switch (count($this->laptopIDs)) {
            case 0:
                throw new BadMethodCallException('There are no laptops');
            case 1:
                $this->log->info('…on 1 laptop…');
                break;
            default:
                $this->log->info('…on %s laptops…', count($this->laptopIDs));
                break;
        }

        if ($this->config['installApplications']['addLicense'] === true) {
            $this->log->info('…with licenses');
        } else {
            $this->log->info('…without licenses');
        }

        $requests = [];

        foreach ($this->laptopIDs as $laptopID) {
            $amount = mt_rand(
                $this->config['installApplications']['min'],
                $this->config['installApplications']['max']
            );

            for ($index = 0; $index < $amount; $index++) {
                $data = [
                    'application' => $this->pickRandomElement($this->applicationIDs)
                ];

                if ($this->config['installApplications']['addLicense'] === true) {
                    $licenseID = $this->pickRandomElement($this->licenseIDs);

                    $data['assigned_license'] = $this->licenseKeyIDs[$licenseID];
                }

                $requests[] = [
                    'method' => 'cmdb.category.create',
                    'params' => [
                        'objID' => $laptopID,
                        'category' => 'C__CATG__APPLICATION',
                        'data' => $data
                    ]
                ];
            }
        }

        $this->processRequests($requests);

        return $this;
    }

    /**
     * @param array $requests
     *
     * @return array Results
     * @throws Exception on error
     */
    protected function processRequests(array $requests): array {
        $requestCounter = count($requests);

        $limit = $this->config['limitBatchRequests'];

        if ($limit <= 0) {
            $this->log->debug('Unlimited requests per batch allowed');
            $batchCounter = 1;
        } elseif ($limit === 1) {
            $this->log->debug('Only 1 request per batch allowed');
            $batchCounter = $requestCounter;
        } else {
            $this->log->debug('%s requests per batch allowed', $limit);
            $batchCounter = (int) ceil(count($requests) / $limit);
        }

        switch ($requestCounter) {
            case 0:
                throw new BadMethodCallException('Nothing to do');
            case 1:
                $this->log->debug('Process 1 request');
                break;
            default:
                if ($limit <= 0) {
                    $this->log->debug(
                        'Process %s requests in 1 batch',
                        $requestCounter
                    );
                } elseif ($limit === 1) {
                    $this->log->debug(
                        'Process 1 request at once',
                        $requestCounter
                    );
                } elseif ($batchCounter === 1) {
                    $this->log->debug(
                        'Process %s requests in 1 batch',
                        $requestCounter
                    );
                } else {
                    $this->log->debug(
                        'Process %s requests in %s batches',
                        $requestCounter,
                        $batchCounter
                    );
                }
                break;
        }

        if ($requestCounter > self::WARN_AT_HIGH_NUMBER_OF_REQUESTS) {
            $this->log->debug('This could take a while…');
        }

        $offset = 0;
        $round = 1;
        $results = [];

        while ($offset < $requestCounter) {
            $batchRequest = array_slice(
                $requests,
                (int) $offset,
                ($limit > 0) ? $limit : null,
                true
            );

            if ($limit > 0 && $requestCounter >= $limit) {
                if ($offset === 0 && $round === $batchCounter) {
                    $this->log->debug(
                        'Round %s/%s: Process all %s requests',
                        $round,
                        $batchCounter,
                        $limit
                    );
                } elseif ($offset === 0 && $round < $batchCounter) {
                    $this->log->debug(
                        'Round %s/%s: Process first %s requests from %s to %s',
                        $round,
                        $batchCounter,
                        $limit,
                        $offset + 1,
                        $offset + $limit
                    );
                } elseif (($requestCounter - $offset) === 1) {
                    $this->log->debug(
                        'Round %s/%s: Process last request',
                        $round,
                        $batchCounter
                    );
                } elseif (($requestCounter - $offset) > 1 &&
                    ($offset + $limit) > $requestCounter) {
                    $this->log->debug(
                        'Round %s/%s: Process last %s requests',
                        $round,
                        $batchCounter,
                        $requestCounter - $offset
                    );
                } elseif (($requestCounter - $offset) > 1) {
                    $this->log->debug(
                        'Round %s/%s: Process next %s requests from %s to %s',
                        $round,
                        $batchCounter,
                        $limit,
                        $offset + 1,
                        $offset + $limit
                    );
                }
            }

            $results = $results +
                $this->useIdoitAPIFactory()->getAPI()->batchRequest($batchRequest);

            if ($limit <= 0) {
                break;
            }

            $round++;
            $offset += $limit;
        }

        return $results;
    }

    /**
     * @param string $topic
     *
     * @return int[]
     * @throws Exception on error
     */
    protected function generateObjects(string $topic): array {
        $this->log->info('Create %s', $topic);

        $this->assertInteger(
            'amount',
            $this->config[$topic],
            sprintf('Do not know how many %s to create', $topic)
        );

        $requests = [];

        for ($i = 0; $i < $this->config[$topic]['amount']; $i++) {
            $prefix = null;

            if (array_key_exists('prefix', $this->config[$topic])) {
                $prefix = $this->config[$topic]['prefix'];
            }

            $title = $this->genTitle($prefix);

            $requests[] = [
                'method' => 'cmdb.object.create',
                'params' => [
                    'title' => $title,
                    'type' => $this->config[$topic]['objectType']
                ]
            ];
        }

        $result = $this->processRequests($requests);

        $objectIDs = [];

        foreach ($result as $object) {
            $objectIDs[] = (int) $object['id'];
        }

        $this->logStat("Created $topic", count($objectIDs));

        return $objectIDs;
    }

    protected function pickRandomElement(array $haystack) {
        if (count($haystack) === 0) {
            throw new BadMethodCallException('Empty array');
        }

        $index = mt_rand(0, (count($haystack) - 1));

        return $haystack[$index];
    }

    /**
     * Get next free IPv4 address
     *
     * @return string
     *
     * @throws Exception on error
     */
    protected function nextIP(): string {
        if (!isset($this->subnet)) {
            if (count($this->subnetIDs) === 0) {
                throw new Exception('No IP addresses left', 400);
            }
            $this->subnetID = array_shift($this->subnetIDs);
            $this->subnet = new Subnet($this->useIdoitAPIFactory()->getAPI());
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
                throw new Exception(
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

    /**
     * Deploy host to server rack
     *
     * @param int $objectID
     * @param int $neededRUs
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     * @todo Parameter $neededRUs currently unused!?!
     */
    protected function assignHostToRack(int $objectID, int $neededRUs): self {
        if (!isset($this->rackID)) {
            if (count($this->rackIDs) === 0) {
                throw new Exception(
                    'No space left in racks'
                );
            }

            $this->rackID = array_shift($this->rackIDs);

            $rack = $this->useIdoitAPIFactory()->getCMDBCategory()->readFirst(
                $this->rackID,
                'C__CATG__FORMFACTOR'
            );

            $this->rackRUs = $rack['rackunits'];

            $this->rackPos = 0;

            // We need an empty rack:
            $locallyAssignedObjects = $this->useIdoitAPIFactory()->getCMDBCategory()->read(
                $this->rackID,
                'C__CATG__OBJECT'
            );

            if (count($locallyAssignedObjects) > 0) {
                $this->log->debug(
                    'Rack #%s is not empty',
                    $this->rackID
                );

                $this->rackID = null;

                return $this->assignHostToRack(
                    $objectID,
                    $neededRUs
                );
            }

            $this->log->debug(
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
                throw new Exception(
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
            $this->log->debug(
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
            $this->log->debug(
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

    /**
     * Create many objects at once
     *
     * @param array $objects List of object titles and types
     *
     * @return int[] Object identifiers
     *
     * @throws Exception on error
     *
     * @deprecated
     */
    protected function createObjects(array $objects): array {
        $count = count($objects);

        if ($this->config['limitBatchRequests'] > 0 &&
            $count > $this->config['limitBatchRequests']) {
            $this->log->debug(
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
                $objects,
                (int) $index,
                $length,
                true
            );

            if ($this->config['limitBatchRequests'] > 0 &&
                $count > $this->config['limitBatchRequests']) {
                if (count($chunk) === 1) {
                    $this->log->debug('    Create 1 object');
                } else {
                    $this->log->debug('    Create %s objects', count($chunk));
                }
            }

            $objectIDs = array_merge(
                $objectIDs,
                $this->useIdoitAPIFactory()->getCMDBObjects()->create($chunk)
            );

            if ($this->config['limitBatchRequests'] <= 0) {
                break;
            }

            $index += $this->config['limitBatchRequests'];
        }

        return $objectIDs;
    }

    /**
     * Assign objects to location
     *
     * @param int[] $objectIDs List of object identifiers
     * @param int $locationID Location identifier
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function assignObjectsToLocation(array $objectIDs, int $locationID): self {
        return $this->createCategoryEntries(
            $objectIDs,
            'C__CATG__LOCATION',
            [
                'parent' => $locationID
            ]
        );
    }

    /**
     * Create one entry in category for one object
     *
     * @param int $objectID Object identifier
     * @param string $categoryConst Category constant
     * @param array $attributes List of attributes
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createCategoryEntry(int $objectID, string $categoryConst, array $attributes): self {
        $this->log->debug(
            'Create one entry in category "%s" for object #%s',
            $categoryConst,
            $objectID
        );

        $this->useIdoitAPIFactory()->getCMDBCategory()->create($objectID, $categoryConst, $attributes);

        $this->logStat('Created category entries', 1);

        return $this;
    }

    /**
     * Create same entry for multiple objects in one category at once
     *
     * @param int[] $objectIDs List of object identifiers
     * @param string $categoryConst Category constant
     * @param array $attributes List of attributes
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createCategoryEntries(array $objectIDs, string $categoryConst, array $attributes): self {
        $count = count($objectIDs);

        $this->log->debug(
            'Create same entry in category "%s" for %s objects',
            $categoryConst,
            $count
        );

        if ($this->config['limitBatchRequests'] > 0 &&
            $count > $this->config['limitBatchRequests']) {
            $this->log->debug(
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
                $objectIDs,
                (int) $index,
                $length,
                true
            );

            if ($this->config['limitBatchRequests'] > 0 &&
                $count > $this->config['limitBatchRequests']) {
                if (count($chunk) === 1) {
                    $this->log->debug('Push 1 sub request');
                } else {
                    $this->log->debug('Push %s sub requests', count($chunk));
                }
            }

            $this->useIdoitAPIFactory()->getCMDBCategory()->batchCreate(
                $chunk,
                $categoryConst,
                [$attributes]
            );

            if ($this->config['limitBatchRequests'] <= 0) {
                break;
            }

            $index += $this->config['limitBatchRequests'];
        }

        $this->logStat('Created category entries', $count);

        return $this;
    }

    /**
     * Create multiple entries for one object in one category
     *
     * @param int $objectID Object identifier
     * @param string $categoryConst Category constant
     * @param array $attributes List of attributes
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createMultipleCategoryEntriesPerObject(
        int $objectID,
        string $categoryConst,
        array $attributes
    ): self {
        $count = count($attributes);

        $this->log->debug(
            'Create %s entries into category "%s" for object #%s',
            $count,
            $categoryConst,
            $objectID
        );

        if ($this->config['limitBatchRequests'] > 0 &&
            $count > $this->config['limitBatchRequests']) {
            $this->log->debug(
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
                $attributes,
                (int) $index,
                $length,
                true
            );

            if ($this->config['limitBatchRequests'] > 0 &&
                $count > $this->config['limitBatchRequests']) {
                if (count($chunk) === 1) {
                    $this->log->debug('Push 1 sub request');
                } else {
                    $this->log->debug('Push %s sub requests', count($chunk));
                }
            }

            $this->useIdoitAPIFactory()->getCMDBCategory()->batchCreate(
                [$objectID],
                $categoryConst,
                $chunk
            );

            if ($this->config['limitBatchRequests'] <= 0) {
                break;
            }

            $index += $this->config['limitBatchRequests'];
        }

        $this->logStat('Created category entries', $count);

        return $this;
    }

    /**
     * Commit multiple requests in a batch
     *
     * @param array $requests List of requests
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function sendBatchRequest(array $requests): self {
        $count = count($requests);

        switch ($count) {
            case 0:
                $this->log->debug('    Send no sub request in a batch request');
                break;
            case 1:
                $this->log->debug('    Send 1 sub request in a batch request');
                break;
            default:
                $this->log->debug(
                    '    Send %s sub requests in a batch request',
                    $count
                );
                break;
        }

        if ($this->config['limitBatchRequests'] > 0 &&
            $count > $this->config['limitBatchRequests']) {
            $this->log->debug(
                '    Batch requests are limited to %s sub requests',
                $this->config['limitBatchRequests']
            );
        }

        $index = 0;

        while ($index < $count) {
            if ($this->config['limitBatchRequests'] > 0) {
                $chunk = array_slice(
                    $requests,
                    (int) $index,
                    $this->config['limitBatchRequests'],
                    true
                );

                $num = count($chunk);
                $left = $count - $index - $num;

                if ($num === 0) {
                    break;
                } elseif ($index === 0) {
                    if ($num === 1) {
                        if ($left === 0) {
                            $this->log->debug('        Push 1 sub request');
                        } elseif ($left === 1) {
                            $this->log->debug('        Push first sub request; 1 left');
                        } else {
                            $this->log->debug('        Push first sub request; %s left', $left);
                        }
                    } else {
                        if ($left === 0) {
                            $this->log->debug('        Push %s sub requests', $num);
                        } elseif ($left === 1) {
                            $this->log->debug('        Push first %s sub requests; 1 left', $num);
                        } else {
                            $this->log->debug('        Push first %s sub requests; %s left', $num, $left);
                        }
                    }
                } elseif ($index > 0) {
                    if ($num === 1) {
                        if ($left === 0) {
                            $this->log->debug('        Push last sub request');
                        } elseif ($left === 1) {
                            $this->log->debug('        Push next sub request; 1 left');
                        } else {
                            $this->log->debug('        Push next 1 sub request; %s left', $left);
                        }
                    } else {
                        if ($left === 0) {
                            $this->log->debug('        Push last %s sub requests', $num);
                        } elseif ($left === 1) {
                            $this->log->debug('        Push next %s sub requests; 1 left', $num);
                        } else {
                            $this->log->debug('        Push next %s sub requests; %s left', $num, $left);
                        }
                    }
                }

                $this->useIdoitAPIFactory()->getAPI()->batchRequest($chunk);

                $index += $this->config['limitBatchRequests'];
            } else {
                $this->useIdoitAPIFactory()->getAPI()->batchRequest($requests);
                break;
            }
        }

        return $this;
    }

    /**
     * Log statistics
     *
     * @param string $key Key
     * @param int $value Value
     */
    protected function logStat(string $key, int $value) {
        if (!array_key_exists($key, $this->statistics)) {
            $this->statistics[$key] = $value;
        } else {
            $this->statistics[$key] += $value;
        }
    }

    /**
     * Generate random string
     *
     * @param string $prefix [optional] Prefix
     * @param string $suffix [optional] Suffix
     *
     * @return string
     */
    protected function genTitle(string $prefix = null, string $suffix = null): string {
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

    /**
     *
     *
     * @param string $needle
     * @param array $haystack
     * @param string $error
     * @param int $min
     *
     * @throws Exception on error
     */
    protected function assertArray(string $needle, array $haystack, string $error, int $min = 1) {
        if (!array_key_exists($needle, $haystack) ||
            !is_array($haystack[$needle]) ||
            count($haystack[$needle]) < $min) {
            throw new Exception(
                $error,
                400
            );
        }
    }

    /**
     *
     *
     * @param string $needle
     * @param array $haystack
     * @param string $error
     *
     * @throws Exception on error
     */
    protected function assertString(string $needle, array $haystack, string $error) {
        if (!array_key_exists($needle, $haystack) ||
            !is_string($haystack[$needle]) ||
            $haystack[$needle] === '') {
            throw new Exception(
                $error,
                400
            );
        }
    }

    /**
     *
     *
     * @param string $needle
     * @param array $haystack
     * @param string $error
     * @param bool $isPositive
     *
     * @throws Exception on error
     */
    protected function assertInteger(string $needle, array $haystack, string $error, bool $isPositive = true) {
        if (!array_key_exists($needle, $haystack) ||
            !is_int($haystack[$needle])) {
            throw new Exception(
                $error,
                400
            );
        }

        if ($isPositive && $haystack[$needle] <= 0) {
            throw new Exception(
                $error,
                400
            );
        }
    }

    /**
     *
     *
     * @param string $needle
     * @param array $haystack
     * @param string $error
     * @param bool $isPositive
     *
     * @throws Exception on error
     */
    protected function assertFloat(string $needle, array $haystack, string $error, bool $isPositive = true) {
        if (!array_key_exists($needle, $haystack) ||
            !is_float($haystack[$needle])) {
            throw new Exception(
                $error,
                400
            );
        }

        if ($isPositive && $haystack[$needle] <= 0) {
            throw new Exception(
                $error,
                400
            );
        }
    }

}
