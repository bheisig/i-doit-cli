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

/**
 * Command "help"
 */
class Help extends Command {

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute() {
        IO::out('Usage: idoit [OPTIONS] [COMMAND]

Commands:

init                Create configuration settings and create cache
status              Current status information
read                Fetch information from your CMDB
search              Find your needle in the haystack called CMDB
random              Create randomized data
nextip              Fetch the next free IP address for a given subnet

Show specific help for these commands:

idoit help [COMMAND]
idoit [COMMAND] --help

Options:

-c [FILE],
--config=[FILE]     Additional configuration settings in a JSON-formatted file

-o [FORMAT],
--output=[FORMAT]   Supported output formats are "plain" (default) and "json"

-h, --help          Show this help
--version           Show version information

Verbosity:

-v                  Be verbose
-V                  Be more verbose
-D                  Debug mode

First steps:

1) idoit init
2) idoit read server/host.example.net/model');

        return $this;
    }

    /**
     * Shows usage of this command
     *
     * @return self Returns itself
     */
    public function showUsage() {
        IO::out('Congratulations! You opened the gate to an unknown world.');
    }

}
