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
class Nextip extends Command {

    protected $freeIPAddresses = [];

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        $value = $this->getQuery();

        if ($value === '') {
            throw new \Exception('Missing SUBNET', 400);
        }

        if (is_numeric($value)) {
            $objectID = (int) $value;
        } else {
            $objectID = $this->useIdoitAPI()->getCMDBObjects()->getID(
                $value,
                $this->config['types']['subnets']
            );
        }

        $next = $this->useIdoitAPI()->getSubnet()->load($objectID)->next();

        $this->log->info($next);

        return $this;
    }

    /**
     * Shows usage of this command
     *
     * @return self Returns itself
     */
    public function showUsage(): self {
        $this->log->info(
            'Usage: %1$s %2$s [OPTIONS] SUBNET

%3$s

SUBNET may be an object title or an object identifier. IPv4 only.

Examples:

1) %1$s %2$s "Global v4"
2) %1$s %2$s 20',
            $this->config['args'][0],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
