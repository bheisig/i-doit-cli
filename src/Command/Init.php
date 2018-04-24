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
use bheisig\cli\Command\Init as BaseInit;

/**
 * Command "init"
 */
class Init extends Command {

    use Cache;

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        try {
            $baseInit = new BaseInit($this->config, $this->log);
            $baseInit->execute();

            $this
                ->clearCache()
                ->createCache();

            $this->log->info('Done.');
        } catch (\Exception $e) {
            $code = $e->getCode();

            if ($code === 0) {
                $code = 500;
            }

            throw new \Exception(
                'Unable to initiate idoitcli: ' .
                    $e->getMessage(),
                    $code
            );
        }

        return $this;
    }

    /**
     * @param string $file
     * @param mixed $value
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function serialize(string $file, $value): self {
        $filePath = $this->getHostDir() . '/' . $file;

        $status = file_put_contents($filePath, serialize($value));

        if ($status === false) {
            throw new \Exception(sprintf(
                'Unable to write to cache file "%s"',
                $filePath
            ));
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function clearCache(): self {
        $hostDir = $this->getHostDir();

        if (!is_dir($hostDir)) {
            $this->log->info(
                'Create cache directory for i-doit instance "%s"',
                $this->config['api']['url']
            );

            $status = mkdir($hostDir, 0775, true);

            if ($status === false) {
                throw new \Exception(sprintf(
                    'Unable to create data directory "%s"',
                    $hostDir
                ));
            }
        } else {
            $this->log->info('Clear cache files');

            $files = new \DirectoryIterator($hostDir);

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $status = @unlink($file->getPathname());

                    if ($status === false) {
                        throw new \Exception(sprintf(
                            'Unable to clear data in "%s". Unable to delete file "%s"',
                            $hostDir,
                            $file->getPathname()
                        ));
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function createCache(): self {
        $this->log->info('Fetch list of object types');

        $objectTypes = $this->useIdoitAPI()->getCMDBObjectTypes()->read();

        $this->serialize(
            'object_types',
            $objectTypes
        );

        $this->log->info('Fetch list of assigned categories');

        $objectTypeIDs = [];

        foreach ($objectTypes as $objectType) {
            $objectTypeIDs[] = (int) $objectType['id'];
        }

        $batchedAssignedCategories = $this->useIdoitAPI()->getCMDBObjectTypeCategories()->batchReadByID($objectTypeIDs);

        for ($i = 0; $i < count($objectTypes); $i++) {
            $this->serialize(
                sprintf(
                    'object_type__%s',
                    $objectTypes[$i]['const']
                ),
                $batchedAssignedCategories[$i]
            );
        }

        $this->log->info('Fetch information about categories');

        $consts = [];

        $categories = [];

        foreach ($batchedAssignedCategories as $assignedCategories) {
            foreach ($assignedCategories as $type => $categoryList) {
                if ($type !== 'catg' && $type !== 'cats') {
                    $this->log->warning('Ignore customized categories');
                    continue;
                }

                foreach ($categoryList as $category) {
                    $consts[] = $category['const'];
                    $categories[$category['const']] = $category;
                }
            }
        }

        if (count($consts) > 0) {
            $batchCategoryInfo = $this->useIdoitAPI()->getCMDBCategoryInfo()->batchRead($consts);

            $categoryConsts = array_keys($consts);

            $counter = 0;

            foreach ($categoryConsts as $categoryConst) {
                $categories[$categoryConst]['properties'] = $batchCategoryInfo[$counter];

                $this->serialize(
                    sprintf(
                        'category__%s',
                        $categoryConst
                    ),
                    $categories[$categoryConst]
                );

                $counter++;
            }
        }

        return $this;
    }

}
