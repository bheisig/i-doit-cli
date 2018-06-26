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

use bheisig\idoitcli\Service\Cache;

/**
 * Command "create"
 */
class Create extends Command {

    use Cache;

    /**
     * Path
     *
     * @var array Indexed array
     */
    protected $path = [];

    /**
     * Object type constant in path
     *
     * @var string
     */
    protected $objectTypeConst;

    /**
     * Processes some routines before the execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function setup(): Command {
        parent::setup();

        $this->path = explode('/', $this->getQuery());

        $objectTypes = $this->getObjectTypes();

        foreach ($objectTypes as $objectType) {
            if (strtolower($objectType['title']) === strtolower($this->path[0])) {
                $this->objectTypeConst = $objectType['const'];
                break;
            }
        }

        return $this;
    }

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        switch (count($this->path)) {
            case 1:
                throw new \Exception('Path has only 1 part');
            case 2:
                if (isset($this->objectTypeConst)) {
                    $objectID = $this->useIdoitAPI()->getCMDBObject()->create(
                        $this->objectTypeConst,
                        $this->path[1]
                    );

                    $this->log->info('Object with ID %s successfully created', $objectID);
                } else {
                    $objects = $this->useIdoitAPI()->fetchObjects(['title' => $this->path[0]]);

                    $this->createCategoryEntries($objects, $this->getCategoryConst($this->path[1]));
                }
                break;
            case 3:
                $objects = $this->useIdoitAPI()->fetchObjects([
                    'type' => $this->objectTypeConst,
                    'title' => $this->path[1]
                ]);

                $this->createCategoryEntries($objects, $this->getCategoryConst($this->path[2]));
                break;
            default:
                throw new \Exception('Path has too many parts');
        }

        return $this;
    }

    /**
     *
     *
     * @param array $objects
     * @param string $category
     *
     * @throws \Exception on error
     */
    protected function createCategoryEntries(array $objects, string $category) {
        switch (count($objects)) {
            case 0:
                throw new \Exception('No object found');
        }

        $attributes = $this->getAttributes();

        $objectIDs = array_map(function ($object) {
            return (int) $object['id'];
        }, $objects);

        $this->useIdoitAPI()->getCMDBCategory()->batchCreate(
            $objectIDs,
            $category,
            [$attributes]
        );

        switch (count($objects)) {
            case 1:
                $this->log->info('Created 1 entry');
                break;
            default:
                $this->log->info('Created %s entries', count($objects));
                break;
        }
    }

    /**
     *
     *
     * @param string $category
     *
     * @return string
     *
     * @throws \Exception on error
     */
    protected function getCategoryConst(string $category): string {
        $categories = $this->getCategories();

        $candidates = [];

        foreach ($categories as $categoryInfo) {
            if (strtolower($categoryInfo['title']) === strtolower($category)) {
                $candidates[] = $categoryInfo;
            }

            if (strtolower($categoryInfo['const']) === strtolower($category)) {
                return $category;
            }
        }

        switch(count($candidates)) {
            case 0:
                throw new \Exception(sprintf(
                    'Unknown category "%s"',
                    $category
                ));
            case 1:
                return $candidates[0]['const'];
            default:
                $this->log->warning('Unambigious category title:');

                foreach ($candidates as $candidate) {
                    $this->log->warning(
                        '    "%s" [%s]',
                        $candidate['title'],
                        $candidate['const']
                    );
                }

                throw new \Exception('Unable to create one or more category entries');
        }
    }

    /**
     * @todo This argument parsing is pretty ugly.
     *
     * @return array
     */
    protected function getAttributes(): array {
        $attributes = [];

        $pathKey = array_search($this->getQuery(), $this->config['args']);
        $options = array_slice(
            $this->config['args'],
            ++$pathKey
        );

        for ($i = 0; $i < count($options); $i+=2) {
            $attribute = substr($options[$i], 2);

            if (array_key_exists(($i+1), $options)) {
                $value = $options[($i + 1)];
                $attributes[$attribute] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Shows usage of command
     *
     * @return self Returns itself
     */
    public function showUsage(): self {
        $this->log->info(
            'Usage: %1$s %2$s [OPTIONS] PATH

%3$s

Add new server "host.example.net":

    %1$s %2$s server/host.example.net
    
Add model for server "host.example.net":

    %1$s %2$s server/host.example.net/model --manufacturer VendorXY --title Model123
    %1$s %2$s host.example.net/model --manufacturer VendorXY --title Model123
    
Add model to all servers:

    %1$s %2$s server/*/model --manufacturer VendorXY --title Model123',
            $this->config['args'][0],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
