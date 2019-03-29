<?php

/**
 * Copyright (C) 2016-19 Benjamin Heisig
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

namespace bheisig\idoitcli\Service;

use \Exception;
use \BadMethodCallException;
use \RuntimeException;

/**
 * i-doit API
 */
class IdoitAPI extends Service {

    /**
     * @var IdoitAPIFactory
     */
    protected $idoitAPIFactory;

    /**
     * Setup service
     *
     * @param IdoitAPIFactory $idoitAPIFactory
     *
     * @return self Returns itself
     */
    public function setUp(IdoitAPIFactory $idoitAPIFactory): self{
        $this->idoitAPIFactory = $idoitAPIFactory;

        return $this;
    }

    /**
     * Fetches objects from i-doit
     *
     * @param array $filter Associative array, see CMDBObjects::read()
     * @param array $categories List of category constants for more information
     * @param int $objectCount How many objects will be fetched? Defaults to 0 (ignore it)
     *
     * @return array Keys: object identifiers; values: object attributes
     *
     * @throws Exception on error
     */
    public function fetchObjects(array $filter, array $categories = [], int $objectCount = 0): array {
        $limit = $this->config['limitBatchRequests'];

        $objects = [];
        $offset = 0;

        if (array_key_exists('title', $filter) && strpos($filter['title'], '*') !== false) {
            $readFilter = array_filter($filter, function ($key) {
                return $key !== 'title';
            }, ARRAY_FILTER_USE_KEY);
        } else {
            $readFilter = $filter;
        }

        while (true) {
            if ($limit > 0) {
                $result = $this->idoitAPIFactory->getCMDBObjects()->read($readFilter, $limit, (int) $offset);

                if ($objectCount > 0 && $objectCount >= $limit) {
                    if ($offset === 0) {
                        $this->log->debug(
                            'Fetch first %s objects from %s to %s',
                            $limit,
                            $offset + 1,
                            $offset + $limit
                        );
                    } elseif (($objectCount - $offset) === 1) {
                        $this->log->debug(
                            'Fetch last object'
                        );
                    } elseif (($objectCount - $offset) > 1 && ($offset + $limit) > $objectCount) {
                        $this->log->debug(
                            'Fetch last %s objects',
                            $objectCount - $offset
                        );
                    } elseif (($objectCount - $offset) > 1) {
                        $this->log->debug(
                            'Fetch next %s objects from %s to %s',
                            $limit,
                            $offset + 1,
                            $offset + $limit
                        );
                    }
                }

                $offset += $limit;
            } else {
                $result = $this->idoitAPIFactory->getCMDBObjects()->read($readFilter);
            }

            if (count($result) === 0) {
                break;
            }

            if (array_key_exists('title', $filter) && strpos($filter['title'], '*') !== false) {
                $result = array_filter($result, function ($object) use ($filter) {
                    return fnmatch($filter['title'], $object['title']);
                });
            }

            $objectIDs = [];

            foreach ($result as $object) {
                $objectID = (int) $object['id'];

                $objectIDs[] = $objectID;

                $objects[$objectID] = [
                    'id' => $objectID,
                    'title' => $object['title'],
                    'type' => (int) $object['type'],
                    'type_title' => $object['type_title'],
                ];
            }

            if (count($categories) > 0) {
                foreach ($categories as $categoryConstant) {
                    $categoryResults = $this
                        ->idoitAPIFactory
                        ->getCMDBCategory()
                        ->batchRead($objectIDs, [$categoryConstant]);

                    $count = 0;
                    foreach ($categoryResults as $categoryResult) {
                        $objects[$objectIDs[$count]][$categoryConstant] = $categoryResult;

                        $count++;
                    }
                }
            }

            if (count($result) < $limit) {
                break;
            }

            if ($limit <= 0) {
                break;
            }
        }

        return $objects;
    }

    /**
     * @param string $candidate
     *
     * @return array Object information, for example:
     * - id (numeric indentifier as string)
     * - title (string)
     *
     * @throws Exception when object not found
     */
    public function identifyObject(string $candidate): array {
        if (is_numeric($candidate) && (int) $candidate > 0) {
            $object = $this->idoitAPIFactory->getCMDBObject()->read((int) $candidate);

            if (count($object) > 0) {
                return $object;
            }

            throw new BadMethodCallException(sprintf(
                'Object not found by numeric identifier %s',
                $candidate
            ));
        }

        $objects = $this->fetchObjects([
            'title' => $candidate
        ]);

        switch (count($objects)) {
            case 0:
                throw new BadMethodCallException(sprintf(
                    'Object not found by title "%s"',
                    $candidate
                ));
            case 1:
                $object = end($objects);
                return $object;
            default:
                throw new RuntimeException(sprintf(
                    'Object title "%s" is ambiguous',
                    $candidate
                ));
        }
    }

    /**
     * Translate object titles into identifiers
     *
     * Keep in mind that there could be objects which
     *
     * - have none of provided titles or
     * - have the same titles.
     *
     * As a result the returned array could have a smaller, bigger or even size as the provided titles array.
     *
     * @param array $titles Object titles as strings
     *
     * @return array List of object identifiers
     *
     * @throws Exception on error
     */
    public function fetchObjectIDsByTitles(array $titles): array {
        $objectdIDs = [];

        switch (count($titles)) {
            case 0:
                throw new BadMethodCallException('Empty list of object titles');
            case 1:
                $title = end($titles);

                $objects = $this->idoitAPIFactory->getCMDBObjects()->read([
                    'title' => $title
                ]);

                foreach ($objects as $object) {
                    $objectdIDs[] = (int) $object['id'];
                }
                break;
            default:
                $requests = [];

                foreach ($titles as $title) {
                    $requests[] = [
                        'method' => 'cmdb.objects.read',
                        'params' => [
                            'filter' => [
                                'title' => $title
                            ]
                        ]
                    ];
                }

                $result = $this->idoitAPIFactory->getAPI()->batchRequest($requests);

                foreach ($result as $objects) {
                    foreach ($objects as $object) {
                        $objectdIDs[] = (int) $object['id'];
                    }
                }
                break;
        }

        return $objectdIDs;
    }

}
