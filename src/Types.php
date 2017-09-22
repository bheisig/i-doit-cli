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
 * Command "types"
 */
class Types extends Command {

    use Cache;

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute() {
        $types = $this->getObjectTypes();

        $types = array_filter($types, [$this, 'filterObjectTypes']);

        usort($types, [$this, 'sort']);

        $groups = $this->group($types);

        $this->formatGroups($groups);

        return $this;
    }

    protected function filterObjectTypes($type) {
        return $type['status'] === '2';
    }

    protected function group($types) {
        $groups = [];

        foreach ($types as $type) {
            if (!array_key_exists($type['type_group_title'], $groups)) {
                $groups[$type['type_group_title']] = [];
            }

            $groups[$type['type_group_title']][] = $type;
        }

        return $groups;
    }

    protected function formatGroups($groups) {
        switch(count($groups)) {
            case 0:
                IO::out('No groups found');
                break;
            case 1:
                IO::out('1 group type found');
                break;
            default:
                IO::out('%s groups found', count($groups));
                break;
        }

        IO::out('');

        foreach ($groups as $group => $types) {
            IO::out('%s:', $group);
            IO::out('');

            $this->formatList($types);
            IO::out('');
        }
    }

    protected function formatList($types) {
        switch(count($types)) {
            case 0:
                IO::out('No object types found');
                break;
            case 1:
                IO::out('1 object type found');
                break;
            default:
                IO::out('%s object types found', count($types));
                break;
        }

        IO::out('');

        array_map(function ($type) {
            IO::out($this->format($type));
        }, $types);
    }

    protected function format($type) {
        return sprintf(
            '%s [%s]',
            $type['title'],
            $type['const']
        );
    }

    protected function sort($a, $b) {
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

%3$s',
            $this->config['basename'],
            $command,
            $this->config['commands'][$command]
        );

        return $this;
    }

}
