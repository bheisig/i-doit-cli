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

use \bheisig\cli\IO;

/**
 * Command "call"
 */
class Call extends Command {

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        $query = $this->getQuery();

        if ($query === '') {
            while (true) {
                $line = IO::in('');

                if ($line === '') {
                    break;
                }

                $query .= $line;
            }
        }

        $request = json_decode(trim($query), true);

        if (!is_array($request)) {
            throw new \Exception('No valid JSON input');
        }

        if (!array_key_exists('method', $request)) {
            throw new \Exception('No RPC method specified');
        }

        $params = null;

        if (array_key_exists('params', $request)) {
            $params = $request['params'];
        }

        $this->useIdoitAPI()->getAPI()->request($request['method'], $params);

        IO::out('');
        IO::out('Request:');
        IO::out('========');
        IO::out($this->useIdoitAPI()->getAPI()->getLastRequestHeaders());
        IO::out(json_encode($this->useIdoitAPI()->getAPI()->getLastRequestContent(), JSON_PRETTY_PRINT));
        IO::out('');
        IO::out('Response:');
        IO::out('=========');
        IO::out($this->useIdoitAPI()->getAPI()->getLastResponseHeaders());
        IO::out(json_encode($this->useIdoitAPI()->getAPI()->getLastResponse(), JSON_PRETTY_PRINT));

        return $this;
    }

    /**
     * Print usage of command
     *
     * @return self Returns itself
     */
    public function printUsage(): self {
        $this->log->info(
            'Usage: %1$s %2$s [OPTIONS] [REQUEST]

%3$s

REQUEST is a JSON-formatted string. Leave empty to read from standard input.

Examples:

1) %1$s %2$s \'{"version": "2.0","method": "idoit.version","params": {"apikey": "c1ia5q","language": "en"},"id": 1}\'
2) echo \'{"version": "2.0","method": "idoit.version","params": {"apikey": "c1ia5q","language": "en"},"id": 1}\' | %1$s %2$s
3) cat request.txt | %1$s %2$s',
            $this->config['args'][0],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
