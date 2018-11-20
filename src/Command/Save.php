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
 * Command "save"
 */
class Save extends Command {

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        // @todo Implement me!

        return $this;
    }

    /**
     * Shows usage of command
     *
     * @return self Returns itself
     */
    public function printUsage(): self {
        $this->log->info(
            <<< EOF
%3\$s

<strong>USAGE</strong>
    \$ %1\$s %2\$s [OPTIONS] [QUERY]

<strong>ARGUMENTS</strong>
    QUERY   <dim>Combination of</dim> <u>object type/object title/category</u>
    
            <u>object type</u> <dim>is the localized name of an object type</dim>
            <u>object title</u> <dim>should always be unique</dim>
            <u>category</u> <dim>is the localized name of the category</dim>

<strong>COMMAND OPTIONS</strong>

    -a <u>ATTRIBUTE=VALUE</u>,         <dim>Localized attribute name ATTRIBUTE</dim>
    --attribute=<u>ATTRIBUTE=VALUE</u> <dim>and its value VALUE</dim>

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
    <dim># Create new object with type "server" and title "mylittleserver":</dim>
    \$ %1\$s %2\$s server/mylittleserver

    <dim># Create/update attributes in a single-value category</dim>
    \$ %1\$s %2\$s server/mylittleserver/model \\
        -a manufacturer=A -a title=123
    \$ %1\$s %2\$s server/mylittleserver/location \\
        -a ru=11

    <dim># Update attributes in a multi-value category</dim>
    \$ %1\$s %2\$s server/mylittleserver/hostaddress/1 \\
        -a ipv4address=192.168.42.23

    <dim># Interactive mode (based on templates)</dim>
    \$ %1\$s %2\$s
    Create new object
    Type? server
    Title? mylittleserver
    Add more attributes [y/N]? y
    [Model] Manufacturer? A
    [Model] Model? 123
    [Hostaddress] Hostname? mylittleserver
    [Location] Location? rackXY
    […]
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
