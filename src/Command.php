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
 * Base command
 */
abstract class Command implements Executes {

    /**
     * Configuration settings
     *
     * @var array Associative array
     */
    protected $config = [];

    /**
     * Cache directory for current i-doit host
     *
     * @var string
     */
    protected $hostDir;

    /**
     * UNIX timestamp when execution starts
     *
     * @var int
     */
    protected $start = 0;

    /**
     * Duration in seconds how long execution has taken time
     *
     * @var int
     */
    protected $executionTime = 0;

    /**
     * Constructor
     *
     * @param array $config Configuration settings
     */
    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Processes some routines before the execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function setup() {
        $this->start = time();

        return $this;
    }

    /**
     * Processes some routines after the execution
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function tearDown() {
        $this->executionTime = time() - $this->start;

        return $this;
    }

    /**
     * Looks for a query from given arguments
     *
     * @return string
     */
    protected function getQuery() {
        $query = '';

        foreach ($this->config['args'] as $index => $arg) {
            if (array_key_exists('command', $this->config) &&
                $arg === $this->config['command'] &&
                array_key_exists(($index + 1), $this->config['args'])) {
                $query = $this->config['args'][$index + 1];
                break;
            }
        }

        return $query;
    }

    /**
     * Shows usage of this command
     *
     * @return self Returns itself
     */
    public function showUsage() {
        $command = strtolower((new \ReflectionClass($this))->getShortName());

        IO::out('Usage: %1$s [OPTIONS] %2$s

%3$s',
            $this->config['basename'],
            $command,
            $this->config['commands'][$command]
        );

        return $this;
    }

}
