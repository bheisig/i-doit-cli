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

namespace bheisig\idoitcli\Command;

use \DirectoryIterator;
use \Exception;

/**
 * Command "cache"
 */
class Cache extends Command {

    /**
     * Execute command
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    public function execute(): self {
        $this->log->info($this->getDescription());

        $this
            ->clearCache()
            ->createCache();

        return $this;
    }

    /**
     * @param string $file
     * @param mixed $value
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function serialize(string $file, $value): self {
        $filePath = $this->useCache()->getHostDir() . '/' . $file;

        $status = file_put_contents($filePath, serialize($value));

        if ($status === false) {
            throw new Exception(sprintf(
                'Unable to write to cache file "%s"',
                $filePath
            ));
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function clearCache(): self {
        $hostDir = $this->useCache()->getHostDir();

        if (!is_dir($hostDir)) {
            $this->log->info(
                'Create cache directory for i-doit instance "%s"',
                $this->config['api']['url']
            );

            $status = mkdir($hostDir, 0775, true);

            if ($status === false) {
                throw new Exception(sprintf(
                    'Unable to create data directory "%s"',
                    $hostDir
                ));
            }
        } else {
            $this->log->info('Clear cache files');

            $files = new DirectoryIterator($hostDir);

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $status = @unlink($file->getPathname());

                    if ($status === false) {
                        throw new Exception(sprintf(
                            'Unable to clear data in "%s". Unable to delete file "%s"',
                            $hostDir,
                            $file->getPathname()
                        ));
                    }
                }
            }
        }

        $this->log->debug(
            '    Stored cache files in directory %s',
            $hostDir
        );

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function createCache(): self {
        $this->log->info('Fetch list of object types');

        $objectTypes = $this->useIdoitAPIFactory()->getCMDBObjectTypes()->read();

        switch (count($objectTypes)) {
            case 0:
                $this->log->notice(
                    '    Found no object types'
                );
                break;
            case 1:
                $this->log->debug(
                    '    Found 1 object type'
                );
                break;
            default:
                $this->log->debug(
                    '    Found %s object types',
                    count($objectTypes)
                );
                break;
        }

        $this->serialize(
            'object_types',
            $objectTypes
        );

        $this->log->info('Fetch list of assigned categories');

        $objectTypeIDs = [];

        foreach ($objectTypes as $objectType) {
            $objectTypeIDs[] = (int) $objectType['id'];
        }

        $batchedAssignedCategories = $this
            ->useIdoitAPIFactory()
            ->getCMDBObjectTypeCategories()
            ->batchReadByID($objectTypeIDs);

        for ($i = 0; $i < count($objectTypes); $i++) {
            switch (count($batchedAssignedCategories[$i])) {
                case 0:
                    $this->log->notice(
                        '    Object type "%s" [%s] has no categories assigned',
                        $objectTypes[$i]['title'],
                        $objectTypes[$i]['const']
                    );
                    break;
                case 1:
                    $this->log->debug(
                        '    Object type "%s" [%s] has 1 category assigned',
                        $objectTypes[$i]['title'],
                        $objectTypes[$i]['const']
                    );
                    break;
                default:
                    $this->log->debug(
                        '    Object type "%s" [%s] has %s categories assigned',
                        $objectTypes[$i]['title'],
                        $objectTypes[$i]['const'],
                        count($batchedAssignedCategories[$i])
                    );
                    break;
            }

            $this->serialize(
                sprintf(
                    'object_type__%s',
                    $objectTypes[$i]['const']
                ),
                $batchedAssignedCategories[$i]
            );
        }

        $this->log->info('Fetch information about categories');

        $categoryConsts = [];

        $categories = [];

        $blacklistedCategories = $this
            ->useIdoitAPIFactory()
            ->getCMDBCategoryInfo()
            ->getVirtualCategoryConstants();

        foreach ($batchedAssignedCategories as $assignedCategories) {
            foreach ($assignedCategories as $type => $categoryList) {
                foreach ($categoryList as $category) {
                    if (in_array($category['const'], $blacklistedCategories)) {
                        continue;
                    }

                    if (array_key_exists($category['const'], $categories)) {
                        continue;
                    }

                    $categoryConsts[] = $category['const'];
                    $categories[$category['const']] = $category;
                }
            }
        }

        switch (count($categoryConsts)) {
            case 0:
                $this->log->notice(
                    '    Found no information about assigned categories'
                );
                break;
            case 1:
                $this->log->debug(
                    '    Found information about 1 category'
                );
                break;
            default:
                $this->log->debug(
                    '    Found information about %s categories',
                    count($categoryConsts)
                );
                break;
        }

        $propertyCounter = 0;

        if (count($categoryConsts) > 0) {
            $batchCategoryInfo = $this
                ->useIdoitAPIFactory()
                ->getCMDBCategoryInfo()
                ->batchRead($categoryConsts);

            $counter = 0;

            foreach ($categoryConsts as $categoryConst) {
                $categories[$categoryConst]['properties'] = $batchCategoryInfo[$counter];

                $propertyCounter += count($batchCategoryInfo[$counter]);

                switch (count($batchCategoryInfo[$counter])) {
                    case 0:
                        $this->log->debug(
                            '    Category "%s" [%s] has no properties',
                            $categories[$categoryConst]['title'],
                            $categoryConst
                        );
                        break;
                    case 1:
                        $this->log->debug(
                            '    Category "%s" [%s] has 1 property',
                            $categories[$categoryConst]['title'],
                            $categoryConst
                        );
                        break;
                    default:
                        $this->log->debug(
                            '    Category "%s" [%s] has %s properties',
                            $categories[$categoryConst]['title'],
                            $categoryConst,
                            count($batchCategoryInfo[$counter])
                        );
                        break;
                }

                if (count($batchCategoryInfo[$counter]) > 0) {
                    $this->serialize(
                        sprintf(
                            'category__%s',
                            $categoryConst
                        ),
                        $categories[$categoryConst]
                    );
                }

                $counter++;
            }
        }

        switch ($propertyCounter) {
            case 0:
                $this->log->notice(
                    'Found no properties over all categories'
                );
                break;
            case 1:
                $this->log->debug(
                    'Found 1 property over all categories'
                );
                break;
            default:
                $this->log->debug(
                    'Found %s properties over all categories',
                    $propertyCounter
                );
                break;
        }

        return $this;
    }

}
