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
 * Command "categories"
 */
class Categories extends Command {

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

        $categories = $this->useCache()->getCategories();

        $categories = $this->filterCategories($categories);

        $types = [
            [
                'type' => 'CATG',
                'title' => 'Global categories',
                'arg' => 'global',
                'active' => true
            ],
            [
                'type' => 'CATS',
                'title' => 'Specific categories',
                'arg' => 'specific',
                'active' => true
            ]
        ];

        $keepActivated = [];

        foreach ($types as $type) {
            if (in_array('--' . $type['arg'], $this->config['args'])) {
                $keepActivated[] = $type['type'];
            }
        }

        if (count($keepActivated) > 0) {
            for ($i = 0; $i < count($types); $i++) {
                if (!in_array($types[$i]['type'], $keepActivated)) {
                    $types[$i]['active'] = false;
                }
            }
        }

        foreach ($types as $typeDetails) {
            if ($typeDetails['active'] === true) {
                // Type casting is necessary because phpstan unfortunately throws an error:
                $this->formatList(
                    (string) $typeDetails['title'],
                    (string) $typeDetails['type'],
                    $categories
                );

                $this->log
                    ->printAsMessage()
                    ->printEmptyLine();
            }
        }

        return $this;
    }

    /**
     *
     * @param array $categories
     *
     * @return array
     *
     * @throws \Exception on error
     */
    protected function filterCategories(array $categories): array {
        $result = [];

        if (in_array('--enabled', $this->config['args'])) {
            $enabledCategories = $this->getEnabledCategories();

            foreach ($categories as $category) {
                if (in_array($category['const'], $enabledCategories)) {
                    $result[] = $category;
                }
            }
        } elseif (in_array('--disabled', $this->config['args'])) {
            $enabledCategories = $this->getEnabledCategories();

            foreach ($categories as $category) {
                if (!in_array($category['const'], $enabledCategories)) {
                    $result[] = $category;
                }
            }
        } else {
            $result = $categories;
        }

        return $result;
    }

    /**
     * Get list of categories which are assigned to one or more object types
     *
     * @return array
     *
     * @throws \Exception on error
     */
    protected function getEnabledCategories(): array {
        $result = [];

        $objectTypes = $this->useCache()->getObjectTypes();

        foreach ($objectTypes as $objectType) {
            $assignedCategories = $this->useCache()->getAssignedCategories($objectType['const']);

            foreach ($assignedCategories as $type => $categories) {
                foreach ($categories as $category) {
                    $result[] = $category['const'];
                }
            }
        }

        return array_unique($result);
    }

    /**
     *
     *
     * @param string $title
     * @param string $type
     * @param array $categories
     */
    protected function formatList(string $title, string $type, array $categories) {
        $this->log
            ->printAsMessage()
            ->info('<strong>%s [%s]:</strong>', $title, $type)
            ->printEmptyLine();

        $categories = $this->filterByType($categories, $type);

        switch (count($categories)) {
            case 0:
                $this->log
                    ->printAsMessage()
                    ->info('No categories found');
                break;
            case 1:
                $this->log
                    ->printAsMessage()
                    ->info('1 category found');
                break;
            default:
                $this->log
                    ->printAsMessage()
                    ->info('%s categories found', count($categories));
                break;
        }

        usort($categories, [$this, 'sortCategories']);

        foreach ($categories as $category) {
            $this->log
                ->printAsOutput()
                ->info($this->formatCategory($category));
        }
    }

    /**
     *
     *
     * @param array $categories
     * @param string $type
     *
     * @return array
     */
    protected function filterByType(array $categories, string $type): array {
        $result = [];

        foreach ($categories as $category) {
            $constPrefix = 'C__' . $type . '__';
            if (strpos($category['const'], $constPrefix) === 0) {
                $result[] = $category;
            }
        }

        return $result;
    }

    /**
     *
     *
     * @param array $category
     *
     * @return string
     */
    protected function formatCategory(array $category): string {
        return sprintf(
            '%s <dim>[%s]</dim>',
            $category['title'],
            $category['const']
        );
    }

    /**
     *
     *
     * @param array $a
     * @param array $b
     *
     * @return int
     */
    protected function sortCategories(array $a, array $b): int {
        return strcmp($a['title'], $b['title']);
    }

    /**
     * Print usage of command
     *
     * @return self Returns itself
     */
    public function printUsage(): self {
        $this->log->info(
            <<< EOF
%3\$s

<strong>USAGE</strong>
    \$ %1\$s %2\$s [OPTIONS]

<strong>COMMAND OPTIONS</strong>
    --enabled           <dim>Only list categories which are assigned to object</dim>
                        <dim>types</dim>
    --disabled          <dim>Only list categories which are not assigned to any</dim>
                        <dim>object type</dim>

    --global            <dim>Only list global categories</dim>
    --specific          <dim>Only list specific categories</dim>

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
    <dim># List all available categories:</dim>
    \$ %1\$s %2\$s
    
    <dim># List global categories which are assigned to one or more object types:</dim>
    \$ %1\$s %2\$s --enabled --global
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
