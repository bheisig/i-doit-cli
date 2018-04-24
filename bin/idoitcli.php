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

namespace bheisig\idoitcli;

use bheisig\cli\App;
use bheisig\cli\IO;

try {
    require_once __DIR__ . '/../vendor/autoload.php';

    $app = new App();

    $app
        ->addConfigSettings([
            'appDir' => __DIR__ . '/..',
            'baseDir' => $_SERVER['HOME'] . '/.idoitcli',
            'dataDir' => $_SERVER['HOME'] . '/.idoitcli/data'
        ]);

    $app
        ->addCommand(
            'call',
            __NAMESPACE__ . '\\Command\\Call',
            'Perform self-defined API requests'
        )
        ->addCommand(
            'categories',
            __NAMESPACE__ . '\\Command\\Categories',
            'Print a list of available categories'
        )
        ->addCommand(
            'create',
            __NAMESPACE__ . '\\Command\\Create',
            'Create new object or category entry'
        )
        ->addCommand(
            'fixip',
            __NAMESPACE__ . '\\Command\\Fixip',
            'Assign IPs to subnets'
        )
        // Extent basic command for caching:
        ->addCommand(
            'init',
            __NAMESPACE__ . '\\Command\\Init',
            'Create configuration settings and create cache'
        )
        ->addCommand(
            'nextip',
            __NAMESPACE__ . '\\Command\\Nextip',
            'Fetch the next free IP address for a given subnet'
        )
        ->addCommand(
            'random',
            __NAMESPACE__ . '\\Command\\Random',
            'Create randomized data'
        )
        ->addCommand(
            'read',
            __NAMESPACE__ . '\\Command\\Read',
            'Fetch information from your CMDB'
        )
        ->addCommand(
            'search',
            __NAMESPACE__ . '\\Command\\Search',
            'Find your needle in the haystack called CMDB'
        )
        ->addCommand(
            'show',
            __NAMESPACE__ . '\\Command\\Show',
            'Show everything about an object')
        ->addCommand(
            'status',
            __NAMESPACE__ . '\\Command\\Status',
            'Current status information'
        )
        ->addCommand(
            'types',
            __NAMESPACE__ . '\\Command\\Types',
            'Print a list of available object types and group them'
        );

    $app->run();
} catch (\Exception $e) {
    IO::err($e->getMessage());

    exit(255);
}