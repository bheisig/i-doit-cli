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

use bheisig\cli\HashMap;
use \Exception;
use \RuntimeException;

/**
 * Command "network"
 */
class Network extends Command {

    public const OPTION_FREE = 'free';
    public const OPTION_USED = 'used';

    protected const OPTION_THRESHOLD = 500;
    protected const MAX_LENGTH_IP_ADDRESS = 15;
    protected const EMPTY_CHAR = '█';

    protected $innerWidth = 0;
    protected $paddingLeft = 1;
    protected $paddingRight = 1;
    protected $marginLeft = 4;
    protected $minWidth = 30;
    protected $maxWidth = 80;

    protected $object = [];
    protected $primaryContact = [];
    protected $usedIPAddresses = [];
    protected $networkDefinition = [];
    protected $categoryEntries = [];

    protected $printFree = true;
    protected $printUsed = true;

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
            ->printEmptyLine()
            ->debug('Collect data…');

        $this->object = $this->identifyObjectByArgument();

        $this
            ->printObjectHeader()
            ->loadNetwork()
            ->collectInformation()
            ->printNetworkDefinition()
            ->printContact()
            ->selectUsersChoice()
            ->setDimensions()
            ->printTableHeader()
            ->printTableBody()
            ->printTableFooter();

        return $this;
    }

    protected function printObjectHeader(): self {
        $title = $this->formatObjectTitle();
        $identifier = $this->formatObjectIdentifier();

        $emptySpace = (int)
            $this->maxWidth -
            strlen($title) -
            strlen($identifier);

        if ($emptySpace <= 0) {
            $this->log
                ->printAsOutput()
                ->info($title)
                ->info($identifier);
        } else {
            $this->log
                ->printAsOutput()
                ->info(
                    '%s%s%s',
                    $title,
                    str_repeat(' ', $emptySpace),
                    $identifier
                )
                ->printEmptyLine();
        }

        return $this;
    }

    protected function formatObjectTitle(): string {
        return $this->object['title'];
    }

    protected function formatObjectIdentifier(): string {
        return sprintf(
            '[#%s]',
            $this->object['id']
        );
    }

    protected function loadNetwork(): self {
        try {
            $categoryConstants = [
                'C__CATG__CONTACT',
                'C__CATS__NET_IP_ADDRESSES',
                'C__CATS__NET'
            ];

            $this->categoryEntries = $this->useIdoitAPIFactory()->getCMDBCategory()->batchRead(
                [(int) $this->object['id']],
                $categoryConstants
            );
        } catch (Exception $e) {
            throw new RuntimeException(sprintf(
                'Unable to load information about network "%s" [%s]: %s',
                $this->object['title'],
                (int) $this->object['id'],
                $e->getMessage()
            ));
        }

        return $this;
    }

    /**
     * @return self Returns itself
     * @throws Exception on error
     */
    protected function collectInformation(): self {
        /**
         * Contacts:
         */
        if (array_key_exists(0, $this->categoryEntries) &&
            is_array($this->categoryEntries[0])) {
            $this->primaryContact = $this->getPrimaryContact($this->categoryEntries[0]);
        }

        /**
         * Used IP addresses:
         */

        if (array_key_exists(1, $this->categoryEntries) &&
            is_array($this->categoryEntries[1])) {
            $this->usedIPAddresses = $this->getHardware($this->categoryEntries[1]);
        }

        /**
         * Network definition:
         */

        if (array_key_exists(2, $this->categoryEntries) &&
            is_array($this->categoryEntries[2]) &&
            array_key_exists(0, $this->categoryEntries[2]) &&
            is_array($this->categoryEntries[2][0])) {
            $this->networkDefinition = $this->getNetworkDefintion($this->categoryEntries[2][0]);
        } else {
            throw new Exception(sprintf(
                'No network definition found for object "%s" [#%s]',
                $this->object['title'],
                $this->object['id']
            ));
        }

        $this->networkDefinition['used'] = count($this->usedIPAddresses);
        $this->networkDefinition['free'] = $this->networkDefinition['amount'] - $this->networkDefinition['used'];

        unset($this->categoryEntries);

        return $this;
    }

    /**
     * @param array $assignments
     * @return array
     * @throws Exception on error
     */
    protected function getHardware(array $assignments): array {
        $hardware = [];

        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                throw new Exception('Invalid IP address assignment');
            }

            // @todo Validate variables!
            $ipAddress = HashMap::getValue('title', $assignment);
            $objectID = (int) HashMap::getValue('object.id', $assignment);
            $title = HashMap::getValue('object.title', $assignment);
            $type = HashMap::getValue('object.type_title', $assignment);

            $hardware[] = [
                'ipAddress' => $ipAddress,
                'id' => $objectID,
                'title' => $title,
                'type' => $type
            ];
        }

        return $hardware;
    }

    /**
     * @param array $network
     * @return array
     * @throws Exception on error
     */
    protected function getNetworkDefintion(array $network): array {
        if (HashMap::hasValue('type.const', $network) === false ||
            HashMap::getValue('type.const', $network) !== 'C__CATS_NET_TYPE__IPV4') {
            throw new Exception(sprintf(
                'Network "%s" [#%s] is not IPv4',
                $this->object['title'],
                $this->object['id']
            ));
        }

        $address = HashMap::getValue('address', $network);
        $cidr = HashMap::getValue('cidr_suffix', $network);

        if ($address === false || $cidr === false) {
            throw new Exception(sprintf(
                'Network "%s" (#%s) has no address',
                $this->object['title'],
                $this->object['id']
            ));
        }

        $amount = pow(2, (32 - (int) $cidr));

        $defaultGateway = HashMap::getValue('gateway.ref_title', $network);

        $vlanObjects = HashMap::getValue('layer2_assignments', $network);
        $vlanIDs = [];

        if (is_array($vlanObjects)) {
            $vlanObjectIDs = [];

            foreach ($vlanObjects as $vlanObject) {
                $vlanObjectID = HashMap::getValue('id', $vlanObject);

                if ($vlanObjectID === false) {
                    throw new Exception('Invalid VLAN assigned to network');
                }

                $vlanObjectIDs[] = (int) $vlanObjectID;
            }

            if (count($vlanObjectIDs) > 0) {
                $vlanIDs = $this->getVLANIdentifiers($vlanObjectIDs);
            }
        }

        $firstAddress = HashMap::getValue('range_from', $network);
        $lastAddress = HashMap::getValue('range_to', $network);

        if (!is_string($firstAddress) || !is_string($lastAddress)) {
            throw new Exception('Invalid IP range found');
        }

        $rangeFrom = ip2long($firstAddress);
        $rangeTo = ip2long($lastAddress);

        $subnet = $this
            ->useIdoitAPIFactory()
            ->getSubnet()
            ->load((int) $this->object['id']);

        return [
            'address' => $address,
            'cidr' => $cidr,
            'amount' => $amount,
            'used' => 0,
            'nextFree' => $subnet->hasNext() ? $subnet->next() : '–',
            'defaultGateway' => $defaultGateway ? $defaultGateway : '–',
            'vlans' => $vlanIDs,
            'rangeFrom' => $rangeFrom,
            'rangeTo' => $rangeTo
        ];
    }

    protected function getVLANIdentifiers(array $objectIDs): array {
        try {
            $vlanIDs = [];

            $results = $this->useIdoitAPIFactory()->getCMDBCategory()->batchRead(
                $objectIDs,
                ['C__CATS__LAYER2_NET']
            );

            foreach ($results as $index => $entries) {
                $vlanID = HashMap::getValue('0.vlan_id', $entries);

                if ($vlanID === false) {
                    continue;
                }

                if (!is_numeric($vlanID) ||
                    (int) $vlanID < 0 ||
                    (int) $vlanID > 4096) {
                    throw new Exception(sprintf(
                        'VLAN object #%s has invalid VLAN ID',
                        $objectIDs[$index]
                    ));
                }

                $vlanIDs[] = $vlanID;
            }

            return $vlanIDs;
        } catch (Exception $e) {
            throw new RuntimeException(sprintf(
                'Unable to load information about network "%s" [%s]: %s',
                $this->object['title'],
                (int) $this->object['id'],
                $e->getMessage()
            ));
        }
    }

    /**
     * @param array $entries
     * @return array
     * @throws Exception on error
     */
    protected function getPrimaryContact(array $entries): array {
        switch (count($entries)) {
            case 0:
                return [];
            default:
                foreach ($entries as $entry) {
                    if (!is_array($entry) ||
                        HashMap::getValue('primary.value', $entry) !== '1') {
                        continue;
                    }

                    $name = HashMap::getValue('contact.title', $entry);
                    $email = HashMap::getValue('contact.mail', $entry);
                    $phone = HashMap::getValue('contact.phone_company', $entry);

                    if ($phone === false) {
                        $phone = HashMap::getValue('contact.phone_mobile', $entry);

                        if ($phone === false) {
                            $phone = HashMap::getValue('contact.phone_home', $entry);
                        }
                    }

                    if ($name === false) {
                        throw new Exception('Primary contact has no name');
                    }

                    return [
                        'name' => $name,
                        'email' => $email ? $email : '–',
                        'phone' => $phone ? $phone : '–'
                    ];
                }

                return [];
        }
    }

    protected function printNetworkDefinition(): self {
        $this->log
            ->printAsOutput()
            ->info(
                <<<EOF
    Net address:        %s/%s
    In use:             %s/%s
    Next free address:  %s
    VLAN ID:            %s
    Default gateway:    %s

EOF
                ,
                $this->networkDefinition['address'],
                $this->networkDefinition['cidr'],
                $this->networkDefinition['used'],
                $this->networkDefinition['amount'],
                $this->networkDefinition['nextFree'],
                $this->formatVLANIdentifiers($this->networkDefinition['vlans']),
                $this->networkDefinition['defaultGateway']
            );

        return $this;
    }

    protected function formatVLANIdentifiers(array $vlanIDs): string {
        if (count($vlanIDs) === 0) {
            return '–';
        }

        return implode(', ', $vlanIDs);
    }

    protected function printContact(): self {
        if (count($this->primaryContact) === 0) {
            return $this;
        }

        $this->log
            ->printAsOutput()
            ->info(
                <<<EOF
    Contact:            %s
    E-mail:             %s
    Phone:              %s

EOF
                ,
                $this->primaryContact['name'],
                $this->primaryContact['email'],
                $this->primaryContact['phone']
            )
            ->printEmptyLine();

        return $this;
    }

    /**
     * @return self Returns itself
     * @throws Exception on error
     */
    protected function selectUsersChoice(): self {
        if (array_key_exists('options', $this->config) &&
            is_array($this->config['options']) &&
            array_key_exists(self::OPTION_FREE, $this->config['options']) &&
            $this->config['options'][self::OPTION_FREE] === true) {
            $this->printUsed = false;
        }

        if (array_key_exists('options', $this->config) &&
            is_array($this->config['options']) &&
            array_key_exists(self::OPTION_USED, $this->config['options']) &&
            $this->config['options'][self::OPTION_USED] === true) {
            $this->printFree = false;
        }

        if ($this->printFree &&
            $this->networkDefinition['free'] > self::OPTION_THRESHOLD &&
            $this->useUserInteraction()->isInteractive()) {
            $this->log->notice(
                'This network has %s IPv4 addresses available',
                $this->networkDefinition['free']
            );
            if ($this->useUserInteraction()->askYesNo('Do you like to print them anyway?') === false) {
                $this->printFree = false;
            }
        }

        return $this;
    }

    protected function setDimensions(): self {
        $this->innerWidth =
            $this->maxWidth -
            $this->marginLeft -
            // 2 columns => 3 borders:
            3 -
            // 1st column => IPv4 address:
            $this->paddingLeft -
            self::MAX_LENGTH_IP_ADDRESS -
            $this->paddingRight -
            // 2nd column => hardware component:
            $this->paddingLeft -
            $this->paddingRight;

        return $this;
    }

    protected function printTableHeader(): self {
        $this->log
            ->printAsOutput()
            ->info(
                '%s<dim>╔%s%s%s╦%s%s%s╗</dim>',
                str_repeat(' ', $this->marginLeft),
                str_repeat('=', $this->paddingLeft),
                str_repeat('=', self::MAX_LENGTH_IP_ADDRESS),
                str_repeat('=', $this->paddingRight),
                str_repeat('=', $this->paddingLeft),
                str_repeat('=', $this->innerWidth),
                str_repeat('=', $this->paddingRight)
            );

        return $this;
    }

    protected function printTableBody(): self {
        $rows = [];

        if ($this->printFree && $this->printUsed) {
            $rows = $this->formatCompleteNetwork(false);
        } elseif ($this->printFree && !$this->printUsed) {
            $rows = $this->formatCompleteNetwork(true);
        } elseif (!$this->printFree && $this->printUsed) {
            $rows = $this->formatUsedOnly();
        }

        $this->log
            ->printAsOutput();

        $count = 0;

        foreach ($rows as $row) {
            if ($count !== 0) {
                $this->log
                    ->info(
                        '%s<dim>╠%s%s%s╬%s%s%s╣</dim>',
                        str_repeat(' ', $this->marginLeft),
                        str_repeat('=', $this->paddingLeft),
                        str_repeat('=', self::MAX_LENGTH_IP_ADDRESS),
                        str_repeat('=', $this->paddingRight),
                        str_repeat('=', $this->paddingLeft),
                        str_repeat('=', $this->innerWidth),
                        str_repeat('=', $this->paddingRight)
                    );
            }

            $this->log
                ->info($row);

            $count++;
        }

        return $this;
    }

    protected function printTableFooter(): self {
        $this->log
            ->printAsOutput()
            ->info(
                '%s<dim>╚%s%s%s╩%s%s%s╝</dim>',
                str_repeat(' ', $this->marginLeft),
                str_repeat('=', $this->paddingLeft),
                str_repeat('=', self::MAX_LENGTH_IP_ADDRESS),
                str_repeat('=', $this->paddingRight),
                str_repeat('=', $this->paddingLeft),
                str_repeat('=', $this->innerWidth),
                str_repeat('=', $this->paddingRight)
            );

        return $this;
    }

    protected function formatCompleteNetwork($skipFree = false): array {
        $rangeFrom = $this->networkDefinition['rangeFrom'];
        $rangeTo = $this->networkDefinition['rangeTo'];
        $rows = [];

        for ($current = $rangeFrom; $current <= $rangeTo; $current++) {
            $ipAddress = long2ip($current);
            $hardware = null;

            foreach ($this->usedIPAddresses as $usedIPAddress) {
                if ($usedIPAddress['ipAddress'] === $ipAddress) {
                    $hardware = $usedIPAddress;
                    break;
                }
            }

            if (isset($hardware) && $skipFree) {
                continue;
            } elseif (isset($hardware) && !$skipFree) {
                $rows[] = $this->formatTableRow($hardware);
            } else {
                $rows[] = $this->formatEmptyTableRow($ipAddress);
            }
        }

        return $rows;
    }

    protected function formatUsedOnly(): array {
        $rows = [];

        foreach ($this->usedIPAddresses as $hardware) {
            $rows[] = $this->formatTableRow($hardware);
        }

        return $rows;
    }

    protected function formatTableRow(array $hardware): string {
        $emptySpaceInAddressRow =
            self::MAX_LENGTH_IP_ADDRESS -
            strlen($hardware['ipAddress']);

        return sprintf(
            '%s<dim>║%s</dim>%s%s<dim>%s║%s</dim>%s<dim>%s║</dim>',
            str_repeat(' ', $this->marginLeft),
            str_repeat(' ', $this->paddingLeft),
            str_repeat(' ', $emptySpaceInAddressRow),
            $hardware['ipAddress'],
            str_repeat(' ', $this->paddingRight),
            str_repeat(' ', $this->paddingLeft),
            $this->drawHardwareComponent($hardware),
            str_repeat(' ', $this->paddingRight)
        );
    }

    protected function formatEmptyTableRow(string $ipAddress): string {
        $emptySpaceInAddressRow =
            self::MAX_LENGTH_IP_ADDRESS -
            strlen($ipAddress);

        return sprintf(
            '%s<dim>║</dim>%s%s%s%s<dim>║</dim>%s%s%s<dim>║</dim>',
            str_repeat(' ', $this->marginLeft),
            str_repeat(' ', $this->paddingLeft),
            str_repeat(' ', $emptySpaceInAddressRow),
            $ipAddress,
            str_repeat(' ', $this->paddingRight),
            str_repeat(' ', $this->paddingLeft),
            str_repeat(self::EMPTY_CHAR, $this->innerWidth),
            str_repeat(' ', $this->paddingRight)
        );
    }

    protected function drawHardwareComponent(array $hardware): string {
        $title = $this->drawObjectTitle($hardware['title']);
        $type = $this->drawObjectType($hardware['type']);
        $identifier = $this->drawObjectID($hardware['id']);

        $emptySpace = (int)
            $this->innerWidth -
            strlen($title) -
            strlen($type) -
            strlen($identifier);

        return sprintf(
            '%s%s%s%s',
            $title,
            str_repeat(' ', $emptySpace),
            $type,
            $identifier
        );
    }

    protected function drawObjectType(string $title): string {
        return sprintf(
            '[%s]',
            strtolower($title)
        );
    }

    protected function drawObjectID(int $objectID): string {
        return sprintf('[#%s]', $objectID);
    }

    protected function drawObjectTitle(string $objectTitle): string {
        return $objectTitle;
    }

    /**
     * Print usage of command
     *
     * @return self Returns itself
     */
    public function printUsage(): self {
        $this->log->info(
            <<< EOF
%3\$s

<strong>USAGE</strong>
    \$ %1\$s %2\$s [OPTIONS] [NETWORK]
    
<strong>ARGUMENTS</strong>
    NETWORK             <dim>Object title or numeric identifier</dim>

<strong>COMMAND OPTIONS</strong>
    --%4\$s              <dim>Print only free IP addresses</dim>
    --%5\$s              <dim>Print only used IP addresses</dim>

<strong>COMMON OPTIONS</strong>
    -c <u>FILE</u>,            <dim>Include settings stored in a JSON-formatted</dim>
    --config=<u>FILE</u>       <dim>configuration file FILE; repeat option for more</dim>
                        <dim>than one FILE</dim>
    -s <u>KEY=VALUE</u>,       <dim>Add runtime setting KEY with its VALUE; separate</dim>
    --setting=<u>KEY=VALUE</u> <dim>nested keys with ".", for example "key1.key2=123";</dim>
                        <dim>repeat option for more than one KEY</dim>

    --no-colors         <dim>Do not print colored messages</dim>
    -q, --quiet         <dim>Do not output messages, only errors</dim>
    -v, --verbose       <dim>Be more verbose</dim>

    -h, --help          <dim>Print this help or information about a</dim>
                        <dim>specific command</dim>
    --version           <dim>Print version information</dim>

    -y, --yes           <dim>No user interaction required; answer questions</dim>
                        <dim>automatically with default values</dim>

<strong>EXAMPLES</strong>
    <dim># Select network by its title:</dim>
    \$ %1\$s %2\$s "Cloud Storage VLAN 0815"
    \$ %1\$s %2\$s Cloud\\ Storage\\ VLAN\\ 0815
    \$ %1\$s %2\$s "*0815"

    <dim># …or by its numeric identifier:</dim>
    \$ %1\$s %2\$s 123
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription(),
            self::OPTION_FREE,
            self::OPTION_USED
        );

        return $this;
    }

}
