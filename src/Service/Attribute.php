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

namespace bheisig\idoitcli\Service;

use bheisig\idoitcli\API\Idoit;

/**
 * Attribute handling
 */
class Attribute extends Service {

    const TEXT = 'text';
    const TEXT_AREA = 'text_area';
    const DIALOG = 'dialog';
    const DIALOG_PLUS = 'dialog_plus';
    const YES_NO_DIALOG = 'yes_no_dialog';
    const OBJECT_RELATION = 'object_relation';
    const COORDINATES = 'coordinates';
    const IP_ADDRESS = 'ip_address';
    const UNKNOWN = 'unknown';

    protected $definition = [];
    protected $type = '';

    /**
     * @var \bheisig\idoitcli\API\Idoit
     */
    protected $api;

    public function setUp(array $definition, Idoit $api): self {
        $this->definition = $definition;
        $this->api = $api;

        $this->identifyType();

        return $this;
    }

    /**
     * @param mixed $value Value from i-doit API
     *
     * @return string Encoded value, even if it's empty
     *
     * @throws \Exception on error
     */
    public function encode($value): string {
        if (!$this->isLoaded()) {
            throw new \BadMethodCallException('Missing attribute definition');
        }

        if ($value === null) {
            return '';
        }

        switch ($this->type) {
            case self::TEXT:
                return $value;
            case self::TEXT_AREA:
                // Rich-text editor with HTML:
                return strip_tags($value);
            case self::DIALOG:
            case self::DIALOG_PLUS:
                return $value['title'];
            case self::YES_NO_DIALOG:
                // @todo C__CATG__IP::use_standard_gateway always returns category entry identifier…
                if (!array_key_exists('title', $value)) {
                    return 'no';
                }

                $check = filter_var(
                    $value['title'],
                    FILTER_VALIDATE_BOOLEAN
                );

                return ($check) ? 'yes' : 'no';
            case self::OBJECT_RELATION:
                return $value['title'];
            case self::IP_ADDRESS:
                return $value['ref_title'];
            case self::COORDINATES:
                $longitude = '';
                $latitude = '';

                if (!is_array($value)) {
                    return '';
                }

                if (array_key_exists('longitude', $value) &&
                    is_string($value['longitude'])) {
                    $longitude = $value['longitude'];
                }

                if (array_key_exists('latitude', $value) &&
                    is_string($value['latitude'])) {
                    $latitude = $value['latitude'];
                }

                if ($longitude !== '' && $latitude !== '') {
                    return $longitude . ', ' . $latitude;
                } elseif ($longitude !== '' && $latitude === '') {
                    return $longitude;
                } elseif ($longitude === '' && $latitude !== '') {
                    return $latitude;
                } else {
                    return '';
                }
                break;
            case self::UNKNOWN:
                switch (gettype($value)) {
                    case 'array':
                        if (array_key_exists('title', $value)) {
                            return $value['title'];
                        } elseif (array_key_exists('ref_title', $value)) {
                            return $value['ref_title'];
                        } else {
                            $values = [];

                            foreach ($value as $subObject) {
                                if (is_array($subObject) &&
                                    array_key_exists('title', $subObject)) {
                                    $values[] = $subObject['title'];
                                }

                                if (is_array($subObject)) {
                                    if (array_key_exists('title', $value)) {
                                        $values[] = $subObject['title'];
                                    } elseif (array_key_exists('ref_title', $value)) {
                                        $values[] = $subObject['ref_title'];
                                    }
                                }
                            }

                            return implode(', ', $values);
                        }
                        break;
                    case 'string':
                        // Rich text editor uses HTML:
                        return strip_tags($value);
                    default:
                        return '';
                }
                break;
            default:
                throw new \RuntimeException(sprintf(
                    'Unable to encode value for attribute "%s"',
                    $this->definition['title']
                ));
        }
    }

    /**
     * @param string $value
     *
     * @return mixed
     *
     * @throws \Exception on error
     */
    public function decode(string $value) {
        if (!$this->isLoaded()) {
            throw new \BadMethodCallException('Missing attribute definition');
        }

        if ($this->validateEncoded($value) === false) {
            throw new \BadMethodCallException(sprintf(
                'Invalid value for attribute "%s"',
                $this->definition['title']
            ));
        }

        $decodedValue = null;

        switch ($this->type) {
            case self::TEXT:
            case self::TEXT_AREA:
            case self::IP_ADDRESS:
                $decodedValue = $value;
                break;
            case self::DIALOG:
            case self::DIALOG_PLUS:
                if (is_numeric($value) && (int) $value > 0) {
                    $decodedValue = (int) $value;
                } else {
                    $decodedValue = $value;
                }
                break;
            case self::YES_NO_DIALOG:
                $check = filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );

                $decodedValue = ($check) ? 1 : 0;
                break;
            case self::OBJECT_RELATION:
                if (is_numeric($value) && (int) $value > 0) {
                    $object = $this->api->getCMDBObject()->read((int) $value);

                    if (count($object) === 0) {
                        throw new \RuntimeException(sprintf(
                            'Unable to identify object by identifier "%s"',
                            $value
                        ));
                    }

                    $decodedValue = (int) $object['id'];
                } else {
                    $objects = $this->api->fetchObjects([
                        'title' => $value
                    ]);

                    switch (count($objects)) {
                        case 0:
                            throw new \RuntimeException(sprintf(
                                'Unable to identify object by title "%s"',
                                $value
                            ));
                            break;
                        case 1:
                            $object = end($objects);
                            $decodedValue = (int) $object['id'];
                            break;
                        default:
                            throw new \RuntimeException(sprintf(
                                'Found %s objects. Unable to identify object by title "%s"',
                                count($objects),
                                $value
                            ));
                    }
                }
                break;
            case self::COORDINATES:
                // Ignore!
                break;
            case self::UNKNOWN:
            default:
                throw new \RuntimeException(sprintf(
                    'Unable to encode value for attribute "%s"',
                    $this->definition['title']
                ));
        }

        if ($this->validateDecoded($decodedValue) === false) {
            throw new \BadMethodCallException(sprintf(
                'Invalid value for attribute "%s"',
                $this->definition['title']
            ));
        }

        return $decodedValue;
    }

    public function validateEncoded(string $value): bool {
        if (!$this->isLoaded()) {
            throw new \BadMethodCallException('Missing attribute definition');
        }

        switch ($this->type) {
            case self::TEXT_AREA:
                return strlen($value) <= 65535;
            case self::TEXT:
            case self::DIALOG:
            case self::DIALOG_PLUS:
            case self::OBJECT_RELATION:
            case self::IP_ADDRESS:
                return strlen($value) <= 255;
            case self::YES_NO_DIALOG:
                $check = filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );

                return is_bool($check);
            case self::UNKNOWN:
            case self::COORDINATES:
                return true;
            default:
                throw new \RuntimeException(sprintf(
                    'Unable to validate value for attribute "%s"',
                    $this->definition['title']
                ));
        }
    }

    public function validateDecoded($value): bool {
        if (!$this->isLoaded()) {
            throw new \BadMethodCallException('Missing attribute definition');
        }

        switch ($this->type) {
            case self::TEXT:
            case self::IP_ADDRESS:
                return is_string($value) && strlen($value) <= 255;
            case self::TEXT_AREA:
                return is_string($value) && strlen($value) <= 65535;
            case self::DIALOG:
            case self::DIALOG_PLUS:
            case self::OBJECT_RELATION:
                if (is_string($value) && strlen($value) <= 255) {
                    return true;
                } elseif (is_int($value) && $value > 0) {
                    return true;
                }

                return false;
            case self::YES_NO_DIALOG:
                $check = filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );

                return is_bool($check);
            case self::UNKNOWN:
            case self::COORDINATES:
                return true;
            default:
                throw new \RuntimeException(sprintf(
                    'Unable to validate value for attribute "%s"',
                    $this->definition['title']
                ));
        }
    }

    protected function identifyType(): self {
        if (array_key_exists('ui', $this->definition) &&
            is_array($this->definition['ui']) &&
            array_key_exists('type', $this->definition['ui']) &&
            $this->definition['ui']['type'] === 'text' &&
            array_key_exists('data', $this->definition) &&
            is_array($this->definition['data']) &&
            array_key_exists('type', $this->definition['data']) &&
            $this->definition['data']['type'] === 'text' &&
            array_key_exists('format', $this->definition) &&
            $this->definition['format'] === null) {
            $this->type = self::TEXT;
        } elseif (array_key_exists('ui', $this->definition) &&
            is_array($this->definition['ui']) &&
            array_key_exists('type', $this->definition['ui']) &&
            $this->definition['ui']['type'] === 'textarea') {
            $this->type = self::TEXT_AREA;
        } elseif (array_key_exists('ui', $this->definition) &&
            is_array($this->definition['ui']) &&
            array_key_exists('params', $this->definition['ui']) &&
            is_array($this->definition['ui']['params']) &&
            array_key_exists('p_strPopupType', $this->definition['ui']['params']) &&
            $this->definition['ui']['params']['p_strPopupType'] === 'dialog_plus') {
            $this->type = self::DIALOG_PLUS;
        } elseif (array_key_exists('format', $this->definition) &&
            is_array($this->definition['format']) &&
            array_key_exists('callback', $this->definition['format']) &&
            is_array($this->definition['format']['callback']) &&
            array_key_exists(1, $this->definition['format']['callback']) &&
            $this->definition['format']['callback'][1] === 'get_yes_or_no') {
            $this->type = self::YES_NO_DIALOG;
        } elseif (array_key_exists('format', $this->definition) &&
            is_array($this->definition['format']) &&
            array_key_exists('callback', $this->definition['format']) &&
            is_array($this->definition['format']['callback']) &&
            array_key_exists(1, $this->definition['format']['callback']) &&
            $this->definition['format']['callback'][1] === 'dialog') {
            $this->type = self::DIALOG;
        } elseif (array_key_exists('format', $this->definition) &&
            is_array($this->definition['format']) &&
            array_key_exists('callback', $this->definition['format']) &&
            is_array($this->definition['format']['callback']) &&
            array_key_exists(1, $this->definition['format']['callback']) &&
            $this->definition['format']['callback'][1] === 'object') {
            $this->type = self::OBJECT_RELATION;
        } elseif (array_key_exists('format', $this->definition) &&
            is_array($this->definition['format']) &&
            array_key_exists('callback', $this->definition['format']) &&
            is_array($this->definition['format']['callback']) &&
            array_key_exists(1, $this->definition['format']['callback']) &&
            $this->definition['format']['callback'][1] === 'exportHostname') {
            $this->type = self::TEXT;
        } elseif (array_key_exists('format', $this->definition) &&
            is_array($this->definition['format']) &&
            array_key_exists('callback', $this->definition['format']) &&
            is_array($this->definition['format']['callback']) &&
            array_key_exists(1, $this->definition['format']['callback']) &&
            $this->definition['format']['callback'][1] === 'exportIpReference') {
            $this->type = self::IP_ADDRESS;
        } elseif (array_key_exists('format', $this->definition) &&
            is_array($this->definition['format']) &&
            array_key_exists('callback', $this->definition['format']) &&
            is_array($this->definition['format']['callback']) &&
            array_key_exists(1, $this->definition['format']['callback']) &&
            $this->definition['format']['callback'][1] === 'location') {
            $this->type = self::OBJECT_RELATION;
        } elseif (array_key_exists('format', $this->definition) &&
            is_array($this->definition['format']) &&
            array_key_exists('callback', $this->definition['format']) &&
            is_array($this->definition['format']['callback']) &&
            array_key_exists(1, $this->definition['format']['callback']) &&
            $this->definition['format']['callback'][1] === 'property_callback_gps') {
            $this->type = self::COORDINATES;
        } else {
            $this->type = self::UNKNOWN;
        }

        return $this;
    }

    protected function isLoaded(): bool {
        return count($this->definition) > 0;
    }

}
