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
use \RuntimeException;

/**
 * Command "network"
 */
class Network extends Command {

    public const OPTION_FREE = 'free';
    public const OPTION_USED = 'used';

    protected $innerWidth = 0;
    protected $paddingLeft = 1;
    protected $paddingRight = 1;
    protected $marginLeft = 4;
    protected $minWidth = 30;
    protected $maxWidth = 80;

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

        $object = $this->identifyObjectByArgument();

        $this
            ->loadNetwork($object);
//            ->selectUsersChoice()
//            ->setDimensions()
//            ->printObject($object)
//            ->printContact()
//            ->printNetworkData()
//            ->printTableHeader()
//            ->printTableBody()
//            ->printTableFooter();

        return $this;
    }

    protected function loadNetwork(array $object): self {
        try {
            $categoryConstants = [
                'C__CATG__CONTACT',
                'C__CATS__NET_IP_ADDRESSES'
            ];

            $result = $this->useIdoitAPIFactory()->getCMDBCategory()->batchRead(
                [(int) $object['id']],
                $categoryConstants
            );

            if (count($result) !== count($categoryConstants)) {
                throw new RuntimeException('Unexpected result');
            }
        } catch (Exception $e) {
            throw new RuntimeException(sprintf(
                'Unable to load information about network "%s" [%s]: %s',
                $object['title'],
                (int) $object['id'],
                $e->getMessage()
            ));
        }

        return $this;
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
