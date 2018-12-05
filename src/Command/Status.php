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
 * Command "status"
 */
class Status extends Command {

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

        $info = $this->useIdoitAPIFactory()->getCMDB()->readVersion();

        $this->log
            ->printAsOutput()
            ->info('<strong>About i-doit:</strong>')
            ->info('Version: <strong>i-doit %s %s</strong>', $info['type'], $info['version'])
            ->info('Tenant: <strong>%s</strong>', $info['login']['mandator'])
            ->info('Link: <strong>%s</strong>', str_replace('src/jsonrpc.php', '', $this->config['api']['url']))
            ->info('API entry point: <strong>%s</strong>', $this->config['api']['url'])
            ->printEmptyLine()
            ->info('<strong>About this script:</strong>')
            ->info('Name: <strong>%s</strong>', $this->config['composer']['extra']['name'])
            ->info('Description: <strong>%s</strong>', $this->config['composer']['description'])
            ->info('Version: <strong>%s</strong>', $this->config['composer']['extra']['version'])
            ->info('Website: <strong>%s</strong>', $this->config['composer']['homepage'])
            ->info('License: <strong>%s</strong>', $this->config['composer']['license'])
            ->printEmptyLine()
            ->info('<strong>About you:</strong>')
            ->info('Name: <strong>%s</strong>', $info['login']['name'])
            ->info('User name: <strong>%s</strong>', $info['login']['username'])
            ->info('E-mail: <strong>%s</strong>', $info['login']['mail'])
            ->info('Prefered language: <strong>%s</strong>', $info['login']['language']);

        return $this;
    }

}
