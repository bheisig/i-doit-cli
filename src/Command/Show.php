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

use bheisig\idoitcli\Service\Attribute;

/**
 * Command "show"
 */
class Show extends Command {

    /**
     * Process some routines before executing command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function setup(): Command {
        parent::setup();

        if ($this->cache->isCached() === false) {
            throw new \Exception(sprintf(
                'Unsufficient data. Please run "%s cache" first.',
                $this->config['composer']['extra']['name']
            ), 500);
        }

        return $this;
    }

    /**
     * Execute command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        $this->log->printAsMessage()
            ->info(
                $this->getDescription()
            )
            ->printEmptyLine();

        $query = $this->getQuery();

        $objectID = 0;

        if ($query === '') {
            throw new \Exception('Empty query. Please specify an object title or identifier');
        } elseif (is_numeric($query)) {
            $objectID = (int) $query;

            if ($objectID <= 0) {
                throw new \Exception('Invalid query. Please specify an object identifier');
            }
        } else {
            $objects = $this->useIdoitAPI()->getCMDBObjects()->read(['title' => $query]);

            switch (count($objects)) {
                case 0:
                    throw new \Exception('No object found.');
                    break;
                case 1:
                    $objectID = (int) $objects[0]['id'];
                    break;
                default:
                    $this->log->printAsMessage()
                        ->debug('Found %s objects', count($objects))
                        ->printEmptyLine();

                    foreach ($objects as $object) {
                        $this->log->printAsOutput()->info(
                            '%s: %s',
                            $object['id'],
                            $object['title']
                        );
                    }

                    $this->log->printAsMessage()->printEmptyLine();

                    while (true) {
                        $objectID = (int) $this->userInteraction->askQuestion('Object?');

                        if ($objectID <= 0) {
                            $this->log->printAsMessage()->warning('Please try again.');
                        } else {
                            break;
                        }
                    }
                    break;
            }
        }

        $object = $this->useIdoitAPI()->getCMDBObject()->load($objectID);

        $this->log->printAsOutput()
            ->info('Title: %s', $object['title'])
            ->info('ID: %s', $object['id'])
            ->info('Type: %s', $object['type_title'])
            ->info('SYS-ID: %s', $object['sysid'])
            ->info('CMDB status: %s', $object['cmdb_status_title'])
            ->info('Created: %s', $object['created'])
            ->info('Updated: %s', $object['updated']);

        $categoryTypes = ['catg', 'cats'];

        $blacklistedCategories = $this
            ->useIdoitAPI()
            ->getCMDBCategoryInfo()
            ->getVirtualCategoryConstants();

        foreach ($categoryTypes as $categoryType) {
            if (!array_key_exists($categoryType, $object)) {
                continue;
            }

            foreach ($object[$categoryType] as $category) {
                if (in_array($category['const'], ['C__CATG__RELATION', 'C__CATG__LOGBOOK'])) {
                    continue;
                }

                if (in_array($category['const'], $blacklistedCategories)) {
                    continue;
                }

                $this->log->printAsMessage()->printEmptyLine();

                switch (count($category['entries'])) {
                    case 0:
                        $this->log->printAsMessage()->info(
                            'No entries in category "%s"',
                            $category['title']
                        );
                        continue 2;
                    case 1:
                        $this->log->printAsMessage()->info(
                            '1 entry in category "%s":',
                            $category['title']
                        );
                        break;
                    default:
                        $this->log->printAsMessage()->info(
                            '%s entries in category "%s":',
                            count($category['entries']),
                            $category['title']
                        );
                        break;
                }

                try {
                    $categoryInfo = $this->cache->getCategoryInfo($category['const']);
                } catch (\Exception $e) {
                    $this->log->printAsMessage()->notice($e->getMessage());
                    continue;
                }

                foreach ($category['entries'] as $entry) {
                    $this->log->printAsMessage()->printEmptyLine();

                    foreach ($entry as $attribute => $value) {
                        if (in_array($attribute, ['id', 'objID'])) {
                            continue;
                        }

                        $value = (new Attribute($this->config, $this->log))
                            ->setUp($categoryInfo['properties'][$attribute], $this->useIdoitAPI())
                            ->encode($value);

                        if (!isset($value) || $value === '') {
                            $value = '-';
                        }

                        $this->log->printAsOutput()->info(
                            '%s: %s',
                            $categoryInfo['properties'][$attribute]['title'],
                            $value
                        );
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Print usage of command
     *
     * @return self Returns itself
     */
    public function printUsage(): self {
        $this->log->info(
            'Usage: %1$s %2$s [OPTIONS] [QUERY]

%3$s

QUERY could be any object title or identifier.

Examples:

1) %1$s %2$s myserver
2) %1$s %2$s "My Server"
3) %1$s %2$s 42',
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        $this->log->info(
            <<< EOF
%3\$s

<strong>USAGE</strong>
    \$ %1\$s %2\$s [OPTIONS] [OBJECT]

<strong>ARGUMENTS</strong>
    OBJECT              <dim>Object title or numeric identifier</dim>

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
    <dim># %3\$s by its title:</dim>
    \$ %1\$s %2\$s mylittleserver

    <dim># …or by its numeric identifier:</dim>
    \$ %1\$s %2\$s 42
    
    <dim>Handle spaces in title with care:</dim>
    \$ %1\$s %2\$s "My little server"
    \$ %1\$s %2\$s My\\ little\\ server

    <dim># If argument OBJECT is omitted you'll be asked for it:</dim>
    \$ %1\$s %2\$s
    Object? host01.example.com
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
