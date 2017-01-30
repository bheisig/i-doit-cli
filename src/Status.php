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

use bheisig\idoitapi\Idoit;

class Status extends Command {

    public function execute() {
        $this->initiateAPI();

        $idoit = new Idoit($this->api);

        $info = $idoit->readVersion();

        IO::out('About i-doit:');
        IO::out('Version: i-doit %s %s', $info['type'], $info['version']);
        IO::out('Tenant: %s', $info['login']['mandator']);
        IO::out('API entry point: %s', $this->config['api']['url']);

        IO::out('');

        IO::out('About this script:');
        IO::out('Name: %s', $this->config['project']['title']);
        IO::out('Description: %s', $this->config['project']['description']);
        IO::out('Version: %s', $this->config['project']['version']);
        IO::out('Website: %s', $this->config['composer']['homepage']);
        IO::out('License: %s', $this->config['composer']['license']);

        IO::out('');

        IO::out('About you:');
        IO::out('Name: %s', $info['login']['name']);
        IO::out('Username: %s', $info['login']['username']);
        IO::out('Email: %s', $info['login']['mail']);
        IO::out('Prefered language: %s', $info['login']['language']);

        return $this;
    }

}
