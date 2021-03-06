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

use \Exception;
use \BadMethodCallException;
use \RuntimeException;
use bheisig\cli\Command\Command as BaseCommand;
use bheisig\cli\Config;
use bheisig\cli\JSONFile;
use bheisig\idoitcli\Service\Cache;
use bheisig\idoitcli\Service\HandleAttribute;
use bheisig\idoitcli\Service\IdoitAPI;
use bheisig\idoitcli\Service\IdoitAPIFactory;
use bheisig\idoitcli\Service\PrintData;
use bheisig\idoitcli\Service\Validate;

/**
 * Base class for commands
 */
abstract class Command extends BaseCommand {

    /**
     * @var HandleAttribute
     */
    protected $handleAttribute;

    /**
     * i-doit API
     *
     * @var IdoitAPI
     */
    protected $idoitAPI;

    /**
     * i-doit API factory
     *
     * @var IdoitAPIFactory
     */
    protected $idoitAPIFactory;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var PrintData
     */
    protected $printData;

    /**
     * @var Validate
     */
    protected $validate;

    /**
     * Process some routines before executing command
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    public function setup(): self {
        parent::setup();

        $this->validateConfig();

        return $this;
    }

    /**
     * Load service for user interaction
     *
     * @return Cache
     *
     * @throws Exception when cache is out-dated
     */
    protected function useCache(): Cache {
        if (!isset($this->cache)) {
            $this->cache = new Cache($this->config, $this->log);
        }

        if ($this->cache->isCached() === false &&
            $this->config['command'] !== 'cache') {
            throw new RuntimeException(sprintf(
                'Unsufficient data. Please run "%s cache" first.',
                $this->config['composer']['extra']['name']
            ), 500);
        }

        return $this->cache;
    }

    /**
     * Load service for attribute handling
     *
     * @return HandleAttribute
     *
     * @throws Exception on error
     */
    protected function handleAttribute(): HandleAttribute {
        if (!isset($this->handleAttribute)) {
            $this->handleAttribute = (new HandleAttribute($this->config, $this->log))
                ->setUp($this->useIdoitAPI(), $this->useIdoitAPIFactory());
        }

        return $this->handleAttribute;
    }

    /**
     * Load service for i-doit API
     *
     * @return IdoitAPI
     *
     * @throws Exception on error
     */
    protected function useIdoitAPI(): IdoitAPI {
        if (!isset($this->idoitAPI)) {
            $this->idoitAPI = new IdoitAPI($this->config, $this->log);
            $this->idoitAPI->setUp($this->useIdoitAPIFactory());
        }

        return $this->idoitAPI;
    }

    /**
     * Load service for i-doit API factory
     *
     * @return IdoitAPIFactory
     *
     * @throws Exception on error
     */
    protected function useIdoitAPIFactory(): IdoitAPIFactory {
        if (!isset($this->idoitAPIFactory)) {
            $this->idoitAPIFactory = new IdoitAPIFactory($this->config, $this->log);
        }

        return $this->idoitAPIFactory;
    }

    /**
     * Load service for attribute handling
     *
     * @return PrintData
     *
     * @throws Exception on error
     */
    protected function printData(): PrintData {
        if (!isset($this->printData)) {
            $this->printData = new PrintData($this->config, $this->log);
        }

        return $this->printData;
    }

    /**
     * Load service for validate data
     *
     * @return Validate
     */
    protected function useValidate(): Validate {
        if (!isset($this->validate)) {
            $this->validate = new Validate($this->config, $this->log);
        }

        return $this->validate;
    }

    /**
     * Print debug information
     *
     * @param array $arr
     *
     * @param int $nested
     */
    protected function printDebug(array $arr, int $nested = 0) {
        if (count($arr) === 0) {
            $this->log->debug(
                '%s–',
                str_repeat(' ', $nested * 4)
            );

            return;
        }

        foreach ($arr as $key => $value) {
            switch (gettype($value)) {
                case 'array':
                    $this->log->debug(
                        '%s%s:',
                        str_repeat(' ', $nested * 4),
                        $key
                    );

                    $this->printDebug($value, ($nested + 1));
                    break;
                default:
                    $this->log->debug(
                        '%s%s: %s',
                        str_repeat(' ', $nested * 4),
                        $key,
                        $value
                    );
                    break;
            }
        }
    }

    /**
     * @return array Object
     * @throws Exception on error
     */
    protected function identifyObjectByArgument(): array {
        switch (count($this->config['arguments'])) {
            case 0:
                if ($this->useUserInteraction()->isInteractive() === false) {
                    throw new BadMethodCallException(
                        'No object, no visuals'
                    );
                }

                return $this->askForObject();
            case 1:
                return $this->useIdoitAPI()->identifyObject(
                    $this->config['arguments'][0]
                );
            default:
                throw new BadMethodCallException(
                    'Too many arguments; please provide only one object title or numeric identifier'
                );
        }
    }

    /**
     * Ask user for object title or numeric identifier
     *
     * …until object has been identified
     *
     * @return array Object information, otherwise \Exception is thrown
     *
     * @throws Exception on error
     */
    protected function askForObject(): array {
        $answer = $this->useUserInteraction()->askQuestion('Object?');

        if (strlen($answer) === 0) {
            $this->log->warning('Please re-try');
            return $this->askForObject();
        }

        try {
            return $this->useIdoitAPI()->identifyObject($answer);
        } catch (BadMethodCallException $e) {
            $this->log->warning($e->getMessage());
            $this->log->warning('Please re-try');
            return $this->askForObject();
        }
    }

    /**
     * Validate configuration settings
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    protected function validateConfig(): self {
        $file = $this->config['appDir'] . '/config/schema.json';
        $rules = JSONFile::read($file);
        $config = new Config();
        $errors = $config->validate($this->config, $rules);

        if (count($errors) > 0) {
            $this->log->warning('One or more errors found in configuration settings:');

            foreach ($errors as $error) {
                $this->log->warning($error);
            }

            throw new Exception('Cannot proceed unless you fix your configuration');
        }

        return $this;
    }

}
