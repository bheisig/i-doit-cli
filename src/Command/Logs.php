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
 * Command "logs"
 */
class Logs extends Command {

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
     * Print usage of command
     *
     * @return self Returns itself
     */
    public function printUsage(): self {
        $this->log->info(
            <<< EOF
%3\$s

<strong>USAGE</strong>
    \$ %1\$s %2\$s [OPTIONS]

<strong>COMMAND OPTIONS</strong>
    -f, --follow        <dim>Start a continuous stream of logs</dim>
    --id=<u>id</u>             <dim>Filter logs by numeric object identifier</dim>
                        <dim>Repeat to filter by more than one identifiers</dim>
    -n <u>limit</u>,           <dim>Limit number of logs</dim>
    --number=<u>limit</u>
    --since=<u>time</u>        <dim>Filter logs since a specific date/time</dim>
                        <dim>May be everything that can be interpreted as a date/time</dim>
    --title=<u>title</u>       <dim>Filter logs by object title</dim>
                        <dim>Wildcards like "*" and "[ae]" are allowed</dim>
                        <dim>Repeat to filter by more than one titles</dim>
    --type=<u>type</u>         <dim>Filter logs by object type</dim>
                        <dim>Wildcards like "*" and "[ae]" are allowed</dim>
                        <dim>Identify type by its localized name, constant or</dim>
                        <dim>numeric identifier</dim>
                        <dim>Repeat to filter by more than one types</dim>
    
    Any combination of options is allowed.

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
    <dim># Read all logs at once:</dim>
    \$ %1\$s %2\$s

    <dim># Read logs about 2 objects by their identifiers:</dim>
    \$ %1\$s %2\$s --id 23 --id 42

    <dim># Read logs about various objects by similar titles:</dim>
    \$ %1\$s %2\$s --title "host*.example.com"

    <dim># Read logs about various object types:</dim>
    \$ %1\$s %2\$s --type C__OBJTYPE__SERVER --type virtual\\ machine --type 5

    <dim># Follow:</dim>
    \$ %1\$s %2\$s -f

    <dim># Print only logs since today:</dim>
    \$ %1\$s %2\$s --since today

    <dim># Or since a specific date:</dim>
    \$ %1\$s %2\$s --since 2018-01-01
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
