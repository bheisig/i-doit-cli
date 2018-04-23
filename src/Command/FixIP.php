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

namespace bheisig\idoitcli\Command;

use bheisig\idoitcli\Service\Cache;

/**
 * Command "fixip"
 */
class FixIP extends Command {

    use Cache;

    /**
     * Unproper subnets (object IDs)
     *
     * @var int[]
     */
    protected $unproperSubnets = [];

    /**
     * Blacklisted object types (IDs)
     *
     * @var int[]
     */
    protected $blacklistedObjectTypes = [];

    /**
     * Processes some routines before the execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function setup() {
        parent::setup();

        if ($this->isCached() === false) {
            throw new \Exception(sprintf(
                'Unsufficient data. Please run "%s init" first.',
                $this->config['args'][0]
            ), 500);
        }

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
        $dryRun = false;

        if (in_array('--dry-run', $this->config['args'])) {
            $dryRun = true;
        }

        if ($dryRun === true) {
            $this->log->info('We are performing a dry run. No data will be altered.');
        }

        $this->log->info('Fetch all nets');

        $nets = $this->fetchNets();

        $this->log->info('Fetch all object types to use the configurable blacklist');

        $blacklistedObjectTypes = [];
        $objectTypes = $this->getObjectTypes();

        foreach ($objectTypes as $objectType) {
            if (!in_array($objectType['const'], $this->config['fixip']['blacklistedObjectTypes'])) {
                $blacklistedObjectTypes[] = (int) $objectType['id'];
            }
        }

        unset($objectTypes);

        $this->log->info('Fetch general information about all objects');

        $offset = 0;
        $limit = $this->config['limitBatchRequests'];

        if ($limit === 1) {
            $this->log->info('Process 1 object and its IP addresses at once', $limit);
        } else if ($limit > 1) {
            $this->log->info('Process %s objects and its IP addresses at once', $limit);
        }

        $stats = [
            'broken' => 0,
            'lost' => 0,
            'fixed' => 0
        ];

        $hasNext = true;

        while (true) {
            if ($limit > 0) {
                if ($offset === 0 && $limit === 1) {
                    $this->log->info('Fetch first object');
                } else if ($offset > 0 && $limit === 1) {
                    $this->log->info('Fetch next object (%s)', ($offset + 1));
                } else if ($offset === 0) {
                    $this->log->info('Fetch first %s objects', $limit);
                } else {
                    $this->log->info('Fetch next %s objects (%s-%s)', $limit, ($offset + 1), ($limit + $offset));
                }

                $objects = $this->useIdoitAPI()->getCMDBObjects()->read([], $limit, $offset);

                if (count($objects) < $limit) {
                    $hasNext = false;
                }

                $offset += $limit;
            } else {
                $objects = $this->useIdoitAPI()->getCMDBObjects()->read();
            }

            if (count($objects) === 0) {
                break;
            }

            $objects = $this->qualifyObjects($objects);

            if (count($objects) === 0) {
                if ($limit > 0 && $hasNext === true) {
                    continue;
                } else {
                    break;
                }
            }

            $this->log->info('Find objects with lost IP addresses');

            $requests = [];
            $infos = [];

            foreach ($objects as $object) {
                foreach ($object['ips'] as $ipAddress) {
                    $net = null;

                    if (is_array($ipAddress['net'])) {
                        $net = (int)$ipAddress['net']['id'];
                    }

                    $ip = $ipAddress['hostaddress']['ref_title'];
                    $ip2long = ip2long($ip);
                    $type = 'IPv4';

                    switch ($ipAddress['net_type']['const']) {
                        case 'C__CATS_NET_TYPE__IPV4':
                            break;
                        case 'C__CATS_NET_TYPE__IPV6':
                            $this->log->warning(
                                '%s "%s" [%s] has IPv6 address %s which cannot be handled by this script.',
                                ucfirst($object['type']),
                                $object['title'],
                                $object['id'],
                                $ip
                            );

                            $stats['broken']++;

                            continue 2;
                        default:
                            $this->log->warning(
                                'Unknown subnet type "%s"',
                                $ipAddress['net_type']['const']
                            );

                            $stats['broken']++;

                            continue 2;
                    }

                    if ($ip2long === false) {
                        $this->log->warning(
                            '%s "%s" [%s] has an unproper IPv4 address "%s".',
                            ucfirst($object['type']),
                            $object['title'],
                            $object['id'],
                            $ip
                        );

                        $stats['broken']++;

                        continue;
                    }

                    if ($net === null || empty($net)) {
                        $this->log->warning(
                            '%s "%s" [%s] has %s address %s not assigned to any subnet.',
                            ucfirst($object['type']),
                            $object['title'],
                            $object['id'],
                            $type,
                            $ip
                        );
                    } else if (array_key_exists($net, $this->unproperSubnets)) {
                        $this->log->warning(
                            '%s "%s" [%s] has %s address %s assigned to unproper subnet "%s" [%s].',
                            ucfirst($object['type']),
                            $object['title'],
                            $object['id'],
                            $type,
                            $ip,
                            $this->unproperSubnets[$net],
                            $net
                        );
                    } else if (!array_key_exists($net, $nets)) {
                        $this->log->warning(
                            '%s "%s" [%s] has %s address %s assigned to a subnet [%s] which is not a layer-3-object.',
                            ucfirst($object['type']),
                            $object['title'],
                            $object['id'],
                            $type,
                            $ip,
                            $net
                        );
                    } else if ($ip2long < ip2long($nets[$net]['firstIP']) ||
                        $ip2long > ip2long($nets[$net]['lastIP'])
                    ) {
                        $this->log->warning(
                            '%s "%s" [%s] has %s address %s assigned to subnet "%s" [%s] which mask does not fit.',
                            ucfirst($object['type']),
                            $object['title'],
                            $object['id'],
                            $type,
                            $ip,
                            $nets[$net]['title'],
                            $net
                        );
                    } else {
//                        $this->log->info(
//                            '%s "%s" [%s] has %s address %s assigned to subnet "%s" [%s].',
//                            ucfirst($object['type']),
//                            $object['title'],
//                            $object['id'],
//                            $type,
//                            $ip,
//                            $nets[$net]['title'],
//                            $net
//                        );

                        continue;
                    }

                    $stats['lost']++;

                    $this->log->info('Find proper subnet for this lost IP address');

                    $netCandidates = [];

                    foreach ($nets as $net) {
                        if ($net['type'] === 'IPv6') {
                            continue;
                        }

                        if (array_key_exists($net['id'], $this->unproperSubnets)) {
                            continue;
                        }

                        $first = ip2long($net['firstIP']);
                        $last = ip2long($net['lastIP']);

                        if ($ip2long >= $first && $ip2long <= $last) {
                            $this->log->info(
                                'Net "%s" [%s] seems to be a candidate',
                                $net['title'],
                                $net['id']
                            );

                            $netCandidates[] = $net['id'];
                        }
                    }

                    switch (count($netCandidates)) {
                        case 0:
                            $this->log->warning('There is no subnet suitable for this IP address');
                            continue 2;
                        case 1:
                            // Everything is fine.
                            break;
                        default:
                            $this->log->warning('There is more than one subnet suitable for this IP address');
                            continue 2;
                    }

                    $this->log->info('Look for IP address conflicts within this net');

                    foreach ($objects as $anotherObject) {
                        foreach ($anotherObject['ips'] as $anotherIP) {

                            if ($ipAddress['id'] !== $anotherIP['id'] &&
                                $ip === $anotherIP['hostaddress']['ref_title'] &&
                                end($netCandidates) === (int)$anotherIP['net']['id']
                            ) {
                                $this->log->warning(
                                    'Conflict found: object "%s" [%s] with same address %s',
                                    $anotherObject['title'],
                                    $anotherObject['id'],
                                    $anotherIP['hostaddress']['ref_title']
                                );

                                break 3;
                            }
                        }
                    }

                    $this->log->info('No conflicts found');

                    $data = [
                        'category_id' => (int)$ipAddress['id'],
                        'net' => end($netCandidates),
                        'ipv4_address' => $ip,
                        'net_type' => (int)$ipAddress['net_type']['id'],
                        'primary' => (int)$ipAddress['primary']['value'],
                        'active' => (int)$ipAddress['active']['value']
                    ];

                    if (array_key_exists('ipv4_assignment', $ipAddress) &&
                        array_key_exists('id', $ipAddress['ipv4_assignment'])
                    ) {
                        $data['ipv4_assignment'] = (int)$ipAddress['ipv4_assignment']['id'];
                    }

                    if (array_key_exists('hostname', $ipAddress)) {
                        $data['hostname'] = $ipAddress['hostname'];
                    }

                    if (array_key_exists('dns_server', $ipAddress) &&
                        is_array($ipAddress['dns_server']) &&
                        count($ipAddress['dns_server']) > 0
                    ) {
                        $data['dns_server'] = [];

                        foreach ($ipAddress['dns_server'] as $dns) {
                            if (array_key_exists('ref_id', $dns)) {
                                $data['dns_server'][] = (int)$dns['ref_id'];
                            }
                        }
                    }

                    if (array_key_exists('dns_server_address', $ipAddress)) {
                        $data['dns_server_address'] = (int)$ipAddress['dns_server_address'];
                    }

                    if (array_key_exists('dns_domain', $ipAddress) &&
                        is_array($ipAddress['dns_domain']) &&
                        count($ipAddress['dns_domain']) > 0
                    ) {
                        $data['dns_domain'] = [];

                        foreach ($ipAddress['dns_domain'] as $domain) {
                            if (array_key_exists('id', $domain)) {
                                $data['dns_domain'][] = (int)$domain['id'];
                            }
                        }
                    }

                    if (array_key_exists('use_standard_gateway', $ipAddress)) {
                        $data['use_standard_gateway'] = (int)$ipAddress['use_standard_gateway'];
                    }

                    if (array_key_exists('assigned_port', $ipAddress) &&
                        is_array($ipAddress['assigned_port']) &&
                        array_key_exists('id', $ipAddress['assigned_port'])
                    ) {
                        $data['assigned_port'] = (int)$ipAddress['assigned_port']['id'];
                    }

                    if (array_key_exists('assigned_logical_port', $ipAddress) &&
                        is_array($ipAddress['assigned_logical_port']) &&
                        array_key_exists('id', $ipAddress['assigned_logical_port']) &&
                        array_key_exists('type', $ipAddress['assigned_logical_port'])
                    ) {
                        $data['assigned_logical_port'] = $ipAddress['assigned_logical_port']['id'] .
                            '_' . $ipAddress['assigned_logical_port']['type'];
                    }

                    if (array_key_exists('all_ips', $ipAddress)) {
                        $data['all_ips'] = $ipAddress['all_ips'];
                    }

                    if (array_key_exists('primary_fqdn', $ipAddress)) {
                        $data['primary_fqdn'] = $ipAddress['primary_fqdn'];
                    }

                    if (array_key_exists('description', $ipAddress)) {
                        $data['description'] = $ipAddress['description'];
                    }

                    $requests[] = [
                        'method' => 'cmdb.category.update',
                        'params' => [
                            'objID' => $object['id'],
                            'category' => 'C__CATG__IP',
                            'data' => $data
                        ]
                    ];

                    $infos[] = [
                        'objID' => $object['id'],
                        'title' => $object['title'],
                        'ip' => $ip
                    ];
                }
            }

            unset($objects);

            $countedRequests = count($requests);

            if ($countedRequests === 0) {
                $this->log->info('Nothing to do');
            } else if ($dryRun === true) {
                $this->log->warning('We are performing a dry run. No data will be altered.');
            } else {
                if ($countedRequests === 1) {
                    $this->log->info('Assign 1 IP address to suitable net');
                } else {
                    $this->log->info('Assign %s IP addresses to suitable nets', $countedRequests);
                }

                $this->useIdoitAPI()->getAPI()->batchRequest($requests);

                $stats['fixed'] += $countedRequests;
            }

            if ($limit === 0 || $hasNext === false) {
                break;
            }
        }

        $this->log->info('There are %s problem(s) found in IP addresses', $stats['broken']);
        $this->log->info('%s out of %s lost IP addresses could be fixed', $stats['fixed'], $stats['lost']);

        return $this;
    }

    /**
     * @param array $objects
     *
     * @return array
     *
     * @throws \Exception on error
     */
    protected function qualifyObjects(array $objects) {
        $candidates = [];

        $countedObjects = count($objects);

        foreach ($objects as $object) {
            if (in_array((int) $object['type'], $this->blacklistedObjectTypes)) {
                continue;
            }

            // Ignore objects without status 'normal' [2]:
            $status = (int) $object['status'];

            if ($status !== 2) {
                continue;
            }

            $objectID = (int) $object['id'];

            $candidates[$objectID] = [
                'id' => $objectID,
                'title' => $object['title'],
                'type' => $object['type_title'],
                'ips' => []
            ];
        }

        unset($objects);

        $countedCandidates = count($candidates);

        if ($countedCandidates !== $countedObjects) {
            switch ($countedCandidates) {
                case 0:
                    $this->log->info('Reduced to 0 objects');

                    return [];
                case 1:
                    $this->log->info('Reduced to 1 object');
                    break;
                default:
                    $this->log->info('Reduced to %s objects', $countedCandidates);
                    break;
            }
        }

        $this->log->info('Fetch IP addresses');

        $batchResult = $this->useIdoitAPI()->getCMDBCategory()->batchRead(array_keys($candidates), ['C__CATG__IP']);

        $result = [];

        // Ignore objects without IP addresses:
        foreach ($batchResult as $ipAddresses) {
            if (count($ipAddresses) === 0) {
                continue;
            }

            $result[] = $ipAddresses;
        }

        $countedResults = count($result);

        switch(count($result)) {
            case 0:
                $this->log->info('Found no objects with IP addresses');
                break;
            case 1;
                $this->log->info('Found 1 object with IP addresses');
                break;
            default:
                $this->log->info('Found %s objects with IP addresses', count($result));
                break;
        }

        foreach ($result as $ipAddresses) {
            foreach ($ipAddresses as $ipAddress) {
                $refObjId = (int) $ipAddress['objID'];
                $entryID = (int) $ipAddress['id'];

                if (!is_array($ipAddress['primary_hostaddress']) ||
                    count($ipAddress['primary_hostaddress']) === 0 ||
                    empty($ipAddress['primary_hostaddress']['ref_title'])) {
                    continue;
                }

                $candidates[$refObjId]['ips'][$entryID] = $ipAddress;
            }
        }

        unset($result);

        $objectCollection = [];

        foreach ($candidates as $candidate) {
            if (count($candidate['ips']) > 0) {
                $objectCollection[] = $candidate;
            }
        }

        if ($countedResults !== count($objectCollection)) {
            switch (count($objectCollection)) {
                case 0:
                    $this->log->info('Reduced to none objects with IP addresses');
                    break;
                case 1:
                    $this->log->info('Reduced to 1 object with IP addresses');
                    break;
                default:
                    $this->log->info('Reduced to %s objects with IP addresses', count($objectCollection));
                    break;
            }
        }

        return $objectCollection;
    }

    /**
     *
     *
     * @return array
     *
     * @throws \Exception on error
     */
    protected function fetchNets() {
        $objects = $this->useIdoitAPI()->getCMDBObjects()->readByType('C__OBJTYPE__LAYER3_NET');

        $objectCollection = [];
        $subnetCandidates = [];

        foreach ($objects as $object) {
            // Ignore objects without status 'normal' [2]:
            $status = (int) $object['status'];

            if ($status !== 2) {
                continue;
            }

            $objectID = (int) $object['id'];

            // Is this an unproper subnet?
            if (in_array($object['title'], $this->config['fixip']['unproperSubnets'])) {
                $this->unproperSubnets[$objectID] = $object['title'];
            }

            $subnetCandidates[$objectID] = $object['title'];
        }

        $result = $this->useIdoitAPI()->getCMDBCategory()->batchRead(array_keys($subnetCandidates), ['C__CATS__NET']);

        reset($subnetCandidates);

        if (count($result) !== count($subnetCandidates)) {
            throw new \Exception(sprintf(
                'We asked i-doit for %s subnet(s) but got results for %s one(s)',
                count($subnetCandidates),
                count($result)
            ));
        }

        foreach ($result as $subnet) {
            $subnetCandidateTitle = current($subnetCandidates);
            $subnetCandidateID = key($subnetCandidates);
            next($subnetCandidates);

            if (!is_array($subnet) || count($subnet) === 0 || !array_key_exists(0, $subnet)) {
                $this->log->warning(
                    'Category "Net" is not available for object "%s" [%s]',
                    $subnetCandidateTitle,
                    $subnetCandidateID
                );

                continue;
            }

            if (!array_key_exists('objID', $subnet[0])) {
                $this->log->info($subnet[0]);

                throw new \Exception(sprintf(
                    'API answered with a broken result for unknown object'
                ));
            }

            $objectID = (int) $subnet[0]['objID'];

            if (!array_key_exists($objectID, $subnetCandidates)) {
                throw new \Exception(sprintf(
                    'API answered with a result for unwanted object "%s" [%s]',
                    $subnetCandidates[$objectID],
                    $objectID
                ));
            }

            $type = null;

            if (!array_key_exists('type', $subnet[0]) ||
                !is_array($subnet[0]['type']) ||
                !array_key_exists('const', $subnet[0]['type'])) {
                $this->log->warning(
                    'Net type not available for object "%s" [%s]',
                    $subnetCandidates[$objectID],
                    $objectID
                );

                continue;
            }

            switch ($subnet[0]['type']['const']) {
                case 'C__CATS_NET_TYPE__IPV4':
                    $type = 'IPv4';
                    break;
                case 'C__CATS_NET_TYPE__IPV6':
                    $type = 'IPv6';
                    break;
                default:
                    $this->log->warning(
                        'Unknown subnet type "%s" for object "%s" [%s]',
                        $subnet[0]['type']['const'],
                        $subnetCandidates[$objectID],
                        $objectID
                    );

                    continue;
            }

            if (!array_key_exists('range_from', $subnet[0]) ||
                !array_key_exists('range_to', $subnet[0])) {
                $this->log->warning(
                    'Unknown IP range for object "%s" [%s]',
                    $subnetCandidates[$objectID],
                    $objectID
                );

                continue;
            }

            $objectCollection[$objectID] = [
                'id' => $objectID,
                'title' => $subnetCandidates[$objectID],
                'type' => $type,
                'firstIP' => $subnet[0]['range_from'],
                'lastIP' => $subnet[0]['range_to']
            ];
        }

        $this->log->info('Found %s subnets', count($objectCollection));

        return $objectCollection;
    }


    /**
     * Shows usage of command
     *
     * @return self Returns itself
     */
    public function showUsage() {
        $this->log->info('Usage: %1$s [OPTIONS] %2$s [--dry-run]

%3$s

This command searches for objects that have IP addresses in category
"hostaddress" but those IP addresses are not assigned to a specific subnet. If
the script finds such "lost" IP addresses it will try to find proper subnets
and assign them.

i-doit has two subnets "Global v4" and "Global v6". Both are not proper subnets
because they contains the whole internet. Instead you should already use proper
subnets. Each subnet is documented as a "layer-3-net" object.

Sometimes there is an IP address assigned to a subnet but the subnet mask does
not fit or the subnet is not a "layer-3-net" object. In these cases the script
looks for better alternatives.

There could be some pitfalls:

    1) There is no proper subnet.
    2) There is a proper subnet but the IP address in this subnet is already
       taken.
    3) There are more than one proper subnets.
    4) The provided IP address is invalid.

In each case there will be a warning.

However, if there is a proper subnet with no conflicts at all the script will
assign an IP address to this subnet.

Objects and subnets must have the status "normal", otherwise they will be
ignored. Archived objects will not be touched.

This command currently works IPv4-only.


Configuration
-------------

These settings are available within the configuration namespace "%2$s":

blacklistedObjectTypes:
    List of object types that will be ignored; only insert object type
    constants

unproperSubnets:
    List of subnets ("layer-3-net" objects) which are not suitable, for
    example "Global v4" and "Global v6"


Options
-------

    --dry-run   Do not alter anything within i-doit


Output
------

There will be a lot of ouput produced by this command:

    1) Information will be printed to standard output (STDOUT)
    2) Notices, warnings and errors will be printed to standard error (STDERR)

This difference is very useful to find unwanted behavior. For example, write
logs containing only notices, etc.:

    %1$s %2$s 2> /var/log/%2$s.log',
            $this->config['args'][0],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
