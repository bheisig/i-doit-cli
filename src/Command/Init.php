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

use bheisig\cli\Command\Init as BaseInit;

/**
 * Command "init"
 */
class Init extends Command {

    /**
     * Process some routines before executing command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function setup(): Command {
        // Restore basic setup to skip validation of configuration settings:
        $this->start = time();

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
        try {
            $baseInit = new BaseInit($this->config, $this->log);
            $baseInit->execute();

            $this->log
                ->info('Create cache files needed for faster processing:')
                ->printEmptyLine()
                ->info(
                    '    %s cache',
                    $this->config['args'][0]
                );
        } catch (\Exception $e) {
            $code = $e->getCode();

            if ($code === 0) {
                $code = 500;
            }

            throw new \Exception(
                sprintf(
                    'Unable to initiate "%s": ' . $e->getMessage(),
                    $this->config['args'][0]
                ),
                $code
            );
        }

        return $this;
    }

}
