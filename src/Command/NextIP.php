<?php

/**
 * Copyright (C) 2016-18 Benjamin Heisig
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

/**
 * Command "nextip"
 */
class NextIP extends Command {

    protected $freeIPAddresses = [];

    /**
     * Execute command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        $this->log
            ->printAsMessage()
            ->info($this->getDescription())
            ->printEmptyLine();

        switch (count($this->config['arguments'])) {
            case 0:
                if ($this->useUserInteraction()->isInteractive() === false) {
                    throw new \BadMethodCallException(
                        'No object, no IP'
                    );
                }

                $object = $this->askForObject();
                $objectID = (int) $object['id'];
                break;
            case 1:
                $object = $this->useIdoitAPI()->identifyObject(
                    $this->config['arguments'][0]
                );

                $objectID = (int) $object['id'];
                break;
            default:
                throw new \BadMethodCallException(
                    'Too many arguments; please provide only one object title or numeric identifier'
                );
        }

        $next = $this->useIdoitAPIFactory()->getSubnet()->load($objectID)->next();

        $this->log
            ->printAsOutput()
            ->info($next);

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
    \$ %1\$s %2\$s [OPTIONS] [SUBNET]
    
<strong>ARGUMENTS</strong>
    SUBNET              <dim>Object title or numeric identifier</dim>
                        <dim>Must be a "layer-3-net" object</dim>
                        <dim>Only works for IPv4 subnets</dim>

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
    <dim># Print next IPv4 for subnet by its title:</dim>
    \$ %1\$s %2\$s "Global v4"
    \$ %1\$s %2\$s Global\\ v4

    <dim># …or by its numeric identifier:</dim>
    \$ %1\$s %2\$s 20

    <dim># If argument SUBNET is omitted you'll be asked for it:</dim>
    \$ %1\$s %2\$s
    Object? Global v4
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
