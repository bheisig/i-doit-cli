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
        $command = $this->getQuery();

        if ($command === 'help') {
            $this->showUsage();
        } else if (array_key_exists($command, $this->config['commands'])) {
            $class = __NAMESPACE__ . '\\' . ucfirst($command);

            /** @var \bheisig\idoitcli\Executes $instance */
            $instance = new $class($this->config);

            $instance->showUsage();
        } else if ($command === '') {
            $this->showUsage();
        } else {
            IO::err('Unknown command');

            $this->showUsage();
        }

        return $this;
    }

    /**
     * Shows usage of this command
     *
     * @return self Returns itself
     */
    public function showUsage() {
        $commandList = '';

        foreach ($this->config['commands'] as $command => $description) {
            if ($command === 'help') {
                continue;
            }

            $commandList .= PHP_EOL . $command . "\t\t    " . $description;
        }

        IO::out('Usage: %1$s [OPTIONS] [COMMAND]

Commands:
%2$s

Show specific help for these commands:

%1$s help [COMMAND]
%1$s [COMMAND] --help

Options:

-c [FILE],
--config=[FILE]     Additional configuration settings in a JSON-formatted file

-h, --help          Show this help
--version           Show version information

First steps:

1) %1$s init
2) %1$s read server/host.example.net/model',
            $this->config['basename'],
            $commandList);

        return $this;
    }

}
