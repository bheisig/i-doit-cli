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

/**
 * Command "listcategories"
 */
class ListCategories extends Command {

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute() {
        $categories = $this->getCategories();

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

        foreach ($types as $type) {
            if ($type['active'] === true) {
                $this->formatList($type['title'], $type['type'], $categories);
                IO::err('');
            }
        }

        return $this;
    }

    protected function filterCategories($categories) {
        $result = [];

        if (in_array('--enabled', $this->config['args'])) {
            $enabledCategories = $this->getEnabledCategories();

            foreach ($categories as $category) {
                if (in_array($category['const'], $enabledCategories)) {
                    $result[] = $category;
                }
            }
        } else if (in_array('--disabled', $this->config['args'])) {
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

    protected function getEnabledCategories() {
        $result = [];

        $objectTypes = $this->getObjectTypes();

        foreach ($objectTypes as $objectType) {
            $assignedCategories = $this->getAssignedCategories($objectType['const']);

            foreach ($assignedCategories as $type => $categories) {
                foreach ($categories as $category) {
                    $result[] = $category['const'];
                }
            }
        }

        return array_unique($result);
    }

    protected function formatList($title, $type, $categories) {
        IO::err('%s [%s]:', $title, $type);
        IO::err('');

        $categories = $this->filterByType($categories, $type);

        switch(count($categories)) {
            case 0:
                IO::err('No categories found');
                break;
            case 1:
                IO::err('1 category found');
                break;
            default:
                IO::err('%s categories found', count($categories));
                break;
        }

        IO::err('');

        usort($categories, [$this, 'sortCategories']);

        foreach ($categories as $category) {
            IO::out($this->formatCategory($category));
        }
    }

    protected function filterByType($categories, $type) {
        $result = [];

        foreach ($categories as $category) {
            $constPrefix = 'C__' . $type . '__';
            if (strpos($category['const'], $constPrefix) === 0) {
                $result[] = $category;
            }
        }

        return $result;
    }

    protected function formatCategory($category) {
        return sprintf(
            '%s [%s]',
            $category['title'],
            $category['const']
        );
    }

    protected function sortCategories($a, $b) {
        return strcmp($a['title'], $b['title']);
    }

    /**
     * Shows usage of this command
     *
     * @return self Returns itself
     */
    public function showUsage() {
        $command = strtolower((new \ReflectionClass($this))->getShortName());

        IO::out('Usage: %1$s %2$s

%3$s

Custom categories are currently not supported.

Options:

    --enabled   Only list categories which are assigned to object types
    --disabled  Only list categories which are not assigned to any object type
    
    --global    Include global categories (enabled by default)
    --specific  Include specific categories (enabled by default)
    
Examples:

    %1$s %2$s --global --enabled    Only list global categories which are assigned to object types
    %1$s %2$s --specific            List all specific categories',
            $this->config['basename'],
            $command,
            $this->config['commands'][$command]
        );

        return $this;
    }

}
