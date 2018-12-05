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
 * Command "types"
 */
class Types extends Command {

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

        $types = $this->useCache()->getObjectTypes();

        $types = array_filter($types, [$this, 'filterObjectTypes']);

        usort($types, [$this, 'sort']);

        $groups = $this->group($types);

        $this->formatGroups($groups);

        return $this;
    }

    /**
     * Accept only object types with status "normal" [2]
     *
     * @param array $type Object type information
     *
     * @return bool
     */
    protected function filterObjectTypes(array $type): bool {
        return $type['status'] === '2';
    }

    /**
     * Group object types by localized group names
     *
     * @param array $types Object type information
     *
     * @return array
     */
    protected function group(array $types): array {
        $groups = [];

        foreach ($types as $type) {
            if (!array_key_exists($type['type_group_title'], $groups)) {
                $groups[$type['type_group_title']] = [];
            }

            $groups[$type['type_group_title']][] = $type;
        }

        return $groups;
    }

    /**
     * Print object type groups
     *
     * @param array $groups Object type groups
     */
    protected function formatGroups(array $groups) {
        switch (count($groups)) {
            case 0:
                $this->log
                    ->printAsMessage()
                    ->debug('No groups found');
                break;
            case 1:
                $this->log
                    ->printAsMessage()
                    ->debug('1 group type found');
                break;
            default:
                $this->log
                    ->printAsMessage()
                    ->debug('%s groups found', count($groups));
                break;
        }

        foreach ($groups as $group => $types) {
            $this->log
                ->info('<strong>%s:</strong>', $group);

            $this->formatList($types);

            $this->log
                ->printAsMessage()
                ->printEmptyLine();
        }
    }

    /**
     * Print object types
     *
     * @param array $types List of object types
     */
    protected function formatList(array $types) {
        switch (count($types)) {
            case 0:
                $this->log
                    ->printAsMessage()
                    ->debug('No object types found');
                break;
            case 1:
                $this->log
                    ->printAsMessage()
                    ->debug('1 object type found');
                break;
            default:
                $this->log
                    ->printAsMessage()
                    ->debug('%s object types found', count($types));
                break;
        }

        $this->log->printEmptyLine();

        foreach ($types as $type) {
            $this->log
                ->printAsOutput()
                ->info($this->format($type));
        }
    }

    /**
     * Format object type
     *
     * @param array $type Object type information
     *
     * @return string
     */
    protected function format(array $type): string {
        return sprintf(
            '%s <dim>[%s]</dim>',
            $type['title'],
            $type['const']
        );
    }

    /**
     * Sort object types in alphabetical order by their localized titles
     *
     * @param array $a Object type information
     * @param array $b Object type information
     *
     * @return int
     */
    protected function sort(array $a, array $b): int {
        return strcmp($a['title'], $b['title']);
    }

}
