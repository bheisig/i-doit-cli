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

namespace bheisig\idoitcli\Service;

use \Exception;
use \BadMethodCallException;
use \RuntimeException;
use bheisig\cli\Log;

/**
 * Print data
 */
class PrintData extends Service {

    /**
     * Offset
     *
     * @var int Defaults to 0
     */
    protected $offset = 0;

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
     * Set offset
     *
     * @param int $offset Offset
     *
     * @return self Returns itself
     *
     * @throws BadMethodCallException on invalid parameter
     */
    public function setOffset(int $offset): self {
        if ($offset < 0) {
            throw new BadMethodCallException('Offset must be greater or equal zero');
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * Print category entry
     *
     * @param array $entry Entry with key-value pairs of attribute/value
     * @param array $attributeDefinitions Attribute descriptions provided by API method "cmdb.category.info"
     * @param HandleAttribute $handleAttribute Attribute service
     * @param int $level Log level; defaults to "info"
     * @param string $printAs Print log as output (default) or as message
     *
     * @return self Returns itself
     *
     * @throws Exception on error
     */
    public function printEntry(
        array $entry,
        array $attributeDefinitions,
        HandleAttribute $handleAttribute,
        int $level = Log::INFO,
        string $printAs = Log::PRINT_AS_OUTPUT
    ): self {
        if (count($entry) === 0) {
            throw new BadMethodCallException('Entry is empty');
        }

        if (count($attributeDefinitions) === 0) {
            throw new BadMethodCallException('Empty attribute definitions');
        }

        foreach ($entry as $attribute => $value) {
            if ($attribute === 'id' || $attribute === 'objID') {
                continue;
            }

            if (!array_key_exists($attribute, $attributeDefinitions)) {
                continue;
            }

            $handleAttribute->load($attributeDefinitions[$attribute]);

            if ($handleAttribute->ignore() || $handleAttribute->isReadonly()) {
                continue;
            }

            $encodedValue = $handleAttribute->encode($value);

            if (strlen($encodedValue) === 0) {
                $encodedValue = '–';
            }

            if (!array_key_exists('title', $attributeDefinitions[$attribute])) {
                throw new RuntimeException(sprintf(
                    'No localized title found for attribute "%s"',
                    $attribute
                ));
            }

            $attributeTitle = $attributeDefinitions[$attribute]['title'];

            switch ($printAs) {
                case Log::PRINT_AS_OUTPUT:
                    $this->log->printAsOutput();
                    break;
                case Log::PRINT_AS_MESSAGE:
                    $this->log->printAsMessage();
                    break;
            }

            $this->log->event(
                $level,
                '%s%s: <strong>%s</strong>',
                str_repeat(' ', $this->offset),
                $attributeTitle,
                $encodedValue
            );
        }

        return $this;
    }

    public function printDialogEntries(
        array $entries,
        int $level = Log::INFO,
        string $printAs = Log::PRINT_AS_OUTPUT
    ): self {
        switch ($printAs) {
            case Log::PRINT_AS_OUTPUT:
                $this->log->printAsOutput();
                break;
            case Log::PRINT_AS_MESSAGE:
                $this->log->printAsMessage();
                break;
        }

        foreach ($entries as $entry) {
            $this->log->event(
                $level,
                '%s<strong>%s</strong> [%s]',
                str_repeat(' ', $this->offset),
                $entry['title'],
                $entry['id']
            );
        }

        return $this;
    }

}
