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

    /**
     * Executes the command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute() {
        $objectTypes = $this->getObjectTypes();

        $objectTypes = array_filter($objectTypes, [$this, 'filterObjectTypes']);

        usort($objectTypes, [$this, 'sort']);

        $this->formatList($objectTypes);

        return $this;
    }

    protected function filterObjectTypes($objectType) {
        return $objectType['status'] === '2';
    }

    protected function formatList($objectTypes) {
        switch(count($objectTypes)) {
            case 0:
                IO::err('No object types found');
                break;
            case 1:
                IO::err('1 object type found');
                break;
            default:
                IO::err('%s object types found', count($objectTypes));
                break;
        }

        IO::err('');

        array_map(function ($objectType) {
            IO::out($this->format($objectType));
        }, $objectTypes);
    }

    protected function format($objectType) {
        return sprintf(
            '%s [%s]',
            $objectType['title'],
            $objectType['const']
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
