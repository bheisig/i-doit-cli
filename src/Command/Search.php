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

namespace bheisig\idoitcli\Command;

use bheisig\cli\IO;

/**
 * Command "search"
 */
class Search extends Command {

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute() {
        $query = $this->getQuery();

        if ($query === '') {
            throw new \Exception(
                'Bad request. A query string is required.',
                400
            );
        }

        $results = $this->useIdoitAPI()->getCMDB()->search($query);

        switch (count($results)) {
            case 0;
                IO::err('Nothing found');
                break;
            case 1:
                IO::err('Found 1 result');
                break;
            default:
                IO::err('Found %s results', count($results));
                break;
        }

        $baseLink = strstr($this->config['api']['url'], '/src/jsonrpc.php', true);

        foreach ($results as $result) {
            IO::out('');
            IO::out('%s', $result['value']);
            IO::out('Source: %s [%s]', $result['key'], $result['type']);
            IO::out('Link: %s', $baseLink . $result['link']);
        }

        return $this;
    }

    /**
     * Shows usage of command
     *
     * @return self Returns itself
     */
    public function showUsage() {
        $this->log->info(
            'Usage: %1$s [OPTIONS] %2$s [QUERY]

%3$s

QUERY could be any string.

Examples:

1) %1$s %2$s myserver
2) %1$s %2$s "My Server"',
            $this->config['args'][0],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
