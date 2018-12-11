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

use bheisig\idoitcli\Service\Attribute;

/**
 * Command "save"
 */
class Save extends Command {

    /**
     * @var int
     */
    protected $objectTypeID;

    /**
     * @var string
     */
    protected $objectTypeConstant;

    /**
     * @var string
     */
    protected $objectTypeTitle;

    /**
     * @var int
     */
    protected $objectID;

    /**
     * @var string
     */
    protected $objectTitle;

    /**
     * @var array
     */
    protected $object;

    /**
     * @var int
     */
    protected $categoryID;

    /**
     * @var string
     */
    protected $categoryConstant;

    /**
     * @var string
     */
    protected $categoryTitle;

    /**
     * @var array
     */
    protected $categoryAttributes;

    /**
     * @var bool
     */
    protected $multiValue = false;

    /**
     * @var array Attribute key as key and its value as value
     */
    protected $collectedAttributes = [];

    /**
     * @var int
     */
    protected $entryID;

    /**
     * @var array
     */
    protected $entry;

    /**
     * @var array Indexed array of arrays:
     * [
     *     'categoryConstant' => 'C__CATG__MODEL',
     *     'categoryTitle' => 'Model',
     *     'categoryID' => 42,
     *     'attributeKey' => 'serial',
     *     'attributeTitle' => 'Serial number',
     *     'value' => 'abc123'
     * ]
     */
    protected $template = [];

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

        if (array_key_exists(0, $this->config['arguments'])) {
            $this
                ->parseQuery($this->config['arguments'][0]);
        }

        $this
            ->parseAttributes($this->config['options'])
            ->preloadEntry()
            ->analyzeCollectedData()
            ->interviewUser();

        if (!$this->hasCategory() &&
            $this->hasObjectType() &&
            $this->useUserInteraction()->isInteractive()) {
            $this
                ->loadTemplate()
                ->preloadEntriesFromTemplate();
        }

        if ($this->hasTemplate()) {
            $decision = $this->useUserInteraction()->askYesNo(
                'Add more attributes?'
            );

            if ($decision === true) {
                $this->applyTemplate();
            }
        }

        $this->save();

        return $this;
    }

    /**
     * Try to identify what the user wants to do
     *
     * @param string $query Query
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function parseQuery(string $query): self {
        $queryParts = explode('/', $query);

        switch (count($queryParts)) {
            case 1:
                /**
                 * Either it's an object type…
                 *
                 * $ idoitcli save server
                 *
                 * …or an object title:
                 *
                 * $ idoitcli save mylittleserver
                 */
                if ($this->identifyObjectType($queryParts[0]) === false) {
                    $this->identifyObject($queryParts[0]);
                }
                break;
            case 2:
                /**
                 * Either it's either a combination of…
                 *
                 * …object type and title:
                 *
                 * $ idoitcli save server/mylittleserver
                 *
                 * …or object title and category:
                 *
                 * $ idoitcli save mylittleserver/model
                 */
                if ($this->identifyObjectType($queryParts[0]) === false) {
                    $this->identifyObject($queryParts[0]);
                    $this->identifyCategory($queryParts[1]);
                } else {
                    $this->identifyObject($queryParts[1]);
                }
                break;
            case 3:
                /**
                 * It's a either combination of…
                 *
                 * …object type, object title and category name:
                 *
                 * $ idoitcli server/mylittleserver/model
                 *
                 * …or object title, category name and entry identifier:
                 *
                 * $ idoitcli mylittleserver/hostaddress/1
                 */
                if ($this->identifyObjectType($queryParts[0]) === false) {
                    $this->identifyObject($queryParts[0]);
                    $this->identifyCategory($queryParts[1]);
                    $this->identifyEntry($queryParts[2]);
                } else {
                    $this->identifyObject($queryParts[1]);
                    $this->identifyCategory($queryParts[2]);
                }
                break;
            case 4:
                /**
                 * It's a combination of
                 * object type, object title, category name
                 * and entry identifier:
                 *
                 * $ idoitcli server/mylittleserver/hostaddress/1
                 */
                if ($this->identifyObjectType($queryParts[0]) === false) {
                    throw new \BadMethodCallException(sprintf(
                        'Unknown object type "%s"',
                        $queryParts[0]
                    ));
                }

                $this->identifyObject($queryParts[1]);
                $this->identifyCategory($queryParts[2]);
                $this->identifyEntry($queryParts[3]);
                break;
            default:
                throw new \BadMethodCallException(sprintf(
                    'Query "%s" is invalid',
                    $query
                ));
        }

        return $this;
    }

    /**
     * @param array $options Options passed
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function parseAttributes(array $options) {
        $lookFor = ['a', 'attribute'];

        foreach ($lookFor as $option) {
            if (!array_key_exists($option, $options)) {
                continue;
            }

            $candidates = [];

            if (is_array($options[$option])) {
                $candidates = $options[$option];
            } elseif (is_string($options[$option])) {
                $candidates = [$options[$option]];
            }

            foreach ($candidates as $candidate) {
                $parts = explode('=', $candidate);

                if (count($parts) !== 2) {
                    throw new \BadMethodCallException(sprintf(
                        'Invalid attribute "%s" provided as option',
                        $candidate
                    ));
                }

                $keyOrTitle = strtolower(trim($parts[0]));
                $value = trim($parts[1]);

                if (!$this->hasCategory()) {
                    throw new \BadMethodCallException(
                        'You set one or more attributes for an unknown category'
                    );
                }

                $key = '';

                foreach ($this->categoryAttributes as $categoryAttributeKey => $categoryAttribute) {
                    if ($categoryAttributeKey === $keyOrTitle) {
                        $key = $categoryAttributeKey;
                        break;
                    }

                    if (strtolower($categoryAttribute['title']) === $keyOrTitle) {
                        $key = $categoryAttributeKey;
                        break;
                    }
                }

                if (strlen($key) === 0) {
                    throw new \BadMethodCallException(sprintf(
                        'Category "%s" [%s] has no attribute "%s"',
                        $this->categoryTitle,
                        $this->categoryConstant,
                        $keyOrTitle
                    ));
                }

                $this->collectedAttributes[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Try to preload single-value category entry
     *
     * This is needed for printing default values.
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function preloadEntry(): self {
        if (!$this->hasObject()) {
            return $this;
        }

        if (!$this->hasCategory()) {
            return $this;
        }

        if ($this->hasEntry()) {
            return $this;
        }

        $categoryInfo = $this->useCache()->getCategoryInfo($this->categoryConstant);

        if ($categoryInfo['multi_value'] === '1') {
            return $this;
        }

        $entry = $this->useIdoitAPIFactory()->getCMDBCategory()->readFirst(
            $this->objectID,
            $this->categoryConstant
        );

        if (count($entry) === 0) {
            return $this;
        }

        $this->entryID = (int) $entry['id'];
        $this->entry = $entry;

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function analyzeCollectedData(): self {
        $this
            ->reportObjectType()
            ->reportObject()
            ->reportCategory()
            ->reportEntry()
            ->reportAttributes();

        return $this;
    }

    /**
     * @return self Returns itself
     */
    protected function reportObjectType(): self {
        if ($this->hasObjectType()) {
            $this->log->debug(
                'Object type identified: %s [%s]',
                $this->objectTypeTitle,
                $this->objectTypeConstant
            );
        } else {
            $this->log->debug('No object type identified');
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function reportObject(): self {
        if ($this->hasObject()) {
            $this->log->debug(
                'Object identified: %s [%s]',
                $this->objectTitle,
                $this->objectID
            );
        } elseif (isset($this->objectTitle) &&
            strlen($this->objectTitle) > 0) {
            $this->log->debug(
                'Object "%s" not found',
                $this->objectTitle
            );
        } else {
            $this->log->debug('No object identified');
        }

        if ($this->hasObjectType() && $this->hasObject()) {
            if ($this->isObjectToType($this->object, $this->objectTypeID) === false) {
                throw new \BadMethodCallException(sprintf(
                    'Object "%s" [%s] has type "%s" [%s], but "%s" [%s] is given',
                    $this->object['title'],
                    $this->object['id'],
                    $this->object['type_title'],
                    array_key_exists('type', $this->object) ? $this->object['type'] : $this->object['objecttype'],
                    $this->objectTypeTitle,
                    $this->objectTypeConstant
                ));
            }
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function reportCategory(): self {
        if ($this->hasCategory()) {
            $this->log->debug(
                'Category identified : %s [%s]',
                $this->categoryTitle,
                $this->categoryConstant
            );
        } else {
            $this->log->debug('No category identified');
        }

        if ($this->hasObjectType() && $this->hasCategory()) {
            if ($this->isCategoryAssignedToObjectType($this->objectTypeConstant, $this->categoryConstant) === false) {
                throw new \BadMethodCallException(sprintf(
                    'Category "%s" [%s] is not assigned to object type "%s" [%s]',
                    $this->categoryTitle,
                    $this->categoryConstant,
                    $this->objectTypeTitle,
                    $this->objectTypeConstant
                ));
            }
        }

        return $this;
    }

    /**
     * @return self Returns itself
     */
    protected function reportEntry(): self {
        if ($this->hasEntry()) {
            $this->log->debug(
                'Category entry identified by ID: %s',
                $this->entryID
            );
        } else {
            $this->log->debug('No category entry identified');
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function reportAttributes(): self {
        if ($this->hasAttributes()) {
            switch (count($this->collectedAttributes)) {
                case 1:
                    $this->log->debug('1 attribute identified');
                    break;
                default:
                    $this->log->debug(
                        '%s attributes identified',
                        count($this->collectedAttributes)
                    );
                    break;
            }

            foreach ($this->collectedAttributes as $key => $value) {
                $this->log->debug(
                    '    %s [%s]: %s',
                    $this->categoryAttributes[$key]['title'],
                    $key,
                    $value
                );
            }
        } else {
            $this->log->debug('No attributes identified');
        }

        return $this;
    }

    /**
     * Interview user to collect missing attributes
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function interviewUser(): self {
        if (!$this->useUserInteraction()->isInteractive()) {
            return $this;
        }

        $this
            ->askForObjectType()
            ->reportObjectType()
            ->askForObjectTitle()
            ->askForAttributes();

        return $this;
    }

    /**
     * Ask user for object type
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function askForObjectType(): self {
        if (!$this->hasObjectType()) {
            $objectType = $this->useUserInteraction()->askQuestion('Object type?');

            $this->identifyObjectType($objectType);

            if (!$this->hasObjectType()) {
                $this->askForObjectType();
            }
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function askForObjectTitle(): self {
        if (!$this->hasObject() &&
            isset($this->objectTitle) &&
            strlen($this->objectTitle) > 0) {
            if ($this->useValidate()->isOneLiner($this->objectTitle) === false) {
                $this->log->warning('Object title is invalid');
                return $this->askForObjectTitle();
            }
        } elseif (!$this->hasObject()) {
            $this->objectTitle = $this->useUserInteraction()->askQuestion('Object title?');

            if ($this->useValidate()->isOneLiner($this->objectTitle) === false) {
                $this->log->warning('Object title is invalid');
                return $this->askForObjectTitle();
            }

            $objects = $this->useIdoitAPI()->fetchObjects([
                'title' => $this->objectTitle,
                'type' => $this->objectTypeConstant
            ]);

            switch (count($objects)) {
                case 0:
                    $this->log->debug(
                        'Object "%s" with type "%s" [%s] not found. Excellent.',
                        $this->objectTitle,
                        $this->objectTypeTitle,
                        $this->objectTypeConstant
                    );
                    break;
                case 1:
                    $object = end($objects);

                    $this->log->notice(
                        'There is another object [%s] with same title and type',
                        $object['id']
                    );

                    $decision = $this->useUserInteraction()->askYesNo(
                        'Do you like to update it instead of creating a new one?'
                    );

                    if ($decision === true) {
                        $this->object = $object;
                        $this->objectID = (int) $this->object['id'];
                        $this->objectTitle = $this->object['title'];

                        $this->reportObject();
                    }
                    break;
                default:
                    $this->log->notice(
                        'There are another objects with same title and type:'
                    );

                    foreach ($objects as $objectID => $object) {
                        $this->log->notice(
                            '%s: %s',
                            $objectID,
                            $object['title']
                        );
                    }

                    $this->log->notice('Do you like to update one of these?');

                    $decision = (int) $this->useUserInteraction()->askQuestion(
                        'Select object identifier or just enter to create new object'
                    );

                    if (array_key_exists($decision, $objects)) {
                        $this->object = $objects[$decision];
                        $this->objectID = (int) $this->object['id'];
                        $this->objectTitle = $this->object['title'];
                    }

                    $this->reportObject();
                    break;
            }
        }

        return $this;
    }

    /**
     * Ask for category
     *
     * @return string
     *
     * @throws \Exception on error
     */
    protected function askForCategory(): string {
        if ($this->hasObjectType()) {
            $assignedCategories = $this->useCache()->getAssignedCategories($this->objectTypeConstant);

            if (count($assignedCategories) === 0) {
                throw new \BadMethodCallException(sprintf(
                    'Object type "%s" [%s] has no categories assigned',
                    $this->objectTypeTitle,
                    $this->objectTypeConstant
                ));
            }

            $this->log->notice('You need to specify a category');

            $this->log->info(
                'List of available catogories for objecty type "%s" [%s]:',
                $this->objectTypeTitle,
                $this->objectTypeConstant
            );

            foreach ($assignedCategories as $type => $categories) {
                switch ($type) {
                    case 'catg':
                        $this->log->info('    Global categories:');
                        break;
                    case 'cats':
                        $this->log->info('    Specific categories:');
                        break;
                    case 'custom':
                        $this->log->info('    Custom categories:');
                        break;
                }
                foreach ($categories as $category) {
                    $this->log->info(
                        '        %s [%s]',
                        $category['title'],
                        $category['const']
                    );
                }
            }

            return $this->useUserInteraction()->askQuestion(
                'Please select a category:'
            );
        } else {
            return $this->useUserInteraction()->askQuestion(
                'Please specify a category:'
            );
        }
    }

    /**
     * Ask user for category entry
     *
     * @return string Category identifier as string
     *
     * @throws \Exception on error
     */
    protected function askForEntry(): string {
        $entries = $this->useIdoitAPIFactory()->getCMDBCategory()->read(
            $this->objectID,
            $this->categoryConstant
        );

        $this->log->notice(
            'You need to specify an entry for object "%s" [%s] in category "%s" [%s]',
            $this->objectTitle,
            $this->objectID,
            $this->categoryTitle,
            $this->categoryConstant
        );

        switch (count($entries)) {
            case 0:
                $this->log->notice(
                    'No entries found. A new one will be created.'
                );
                return '';
            case 1:
                $this->log->info(
                    '1 entry found:'
                );

                foreach ($entries as $entry) {
                    $this->log->info(
                        '    %s',
                        $entry['id']
                    );
                }

                $answer = $this->useUserInteraction()->askYesNo(
                    'Do you like to update this entry?'
                );

                if ($answer === true) {
                    return $entries[0]['id'];
                } else {
                    $this->log->info('A new entry will be created.');
                }

                return '';
            default:
                $this->log->info(
                    '%s entries found:',
                    count($entries)
                );

                foreach ($entries as $entry) {
                    $this->log->info(
                        '    %s',
                        $entry['id']
                    );
                }

                return $this->useUserInteraction()->askQuestion(
                    'Please select an entry (leave empty to create a new one):'
                );
        }
    }

    /**
     * Ask user for attributes' values
     *
     * Category is specified in:
     * @uses $categoryTitle
     *
     * Attributes are stored to:
     * @uses $collectedAttributes
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function askForAttributes(): self {
        if (!$this->hasCategory()) {
            return $this;
        }

        if ($this->hasAttributes()) {
            return $this;
        }

        foreach ($this->categoryAttributes as $attributeKey => $attributeDefinition) {
            $attribute = (new Attribute($this->config, $this->log))
                    ->setUp($attributeDefinition, $this->useIdoitAPI(), $this->useIdoitAPIFactory());

            if ($attribute->ignore() || $attribute->isReadonly()) {
                continue;
            }

            $defaultValue = '';

            if ($this->hasEntry() &&
                array_key_exists($attributeKey, $this->entry)) {
                $defaultValue = $attribute
                    ->encode($this->entry[$attributeKey]);
            }

            $value = $this->askForAttribute(
                $this->categoryTitle,
                $attributeDefinition,
                $defaultValue
            );

            if (isset($value)) {
                $this->collectedAttributes[$attributeKey] = $value;
            }
        }

        return $this;
    }

    /**
     * Ask user for attribute's value
     *
     * @param string $categoryTitle Category title
     * @param array $attributeDefinition Attribute definition
     * @param string $defaultValue Default value
     *
     * @return mixed Value, otherwise null if skipped by user
     *
     * @throws \Exception on error
     */
    protected function askForAttribute(string $categoryTitle, array $attributeDefinition, $defaultValue = '') {
        if (strlen($defaultValue) > 0) {
            $value = $this->useUserInteraction()->askQuestion(sprintf(
                '[%s] %s [%s]?',
                $categoryTitle,
                $attributeDefinition['title'],
                $defaultValue
            ));

            if (strlen($value) === 0 || $value === $defaultValue) {
                return null;
            }
        } else {
            $value = $this->useUserInteraction()->askQuestion(sprintf(
                '[%s] %s?',
                $categoryTitle,
                $attributeDefinition['title']
            ));

            if (strlen($value) === 0) {
                return null;
            }
        }

        return (new Attribute($this->config, $this->log))
            ->setUp($attributeDefinition, $this->useIdoitAPI(), $this->useIdoitAPIFactory())
            ->decode($value);
    }

    /**
     * Try to identify object type
     *
     * If found set…
     * @see $objectTypeConstant
     * @see $objectTypeID
     * @see $objectTypeTitle
     *
     * @param string $candidate Localized name, constant or numeric identifier
     *
     * @return bool Returns true if found, otherwise false
     *
     * @throws \Exception on error
     */
    protected function identifyObjectType(string $candidate): bool {
        if (strlen($candidate) === 0) {
            return false;
        }

        $objectTypes = $this->useCache()->getObjectTypes();

        if (is_numeric($candidate) && (int) $candidate > 0) {
            $candidateID = (int) $candidate;

            foreach ($objectTypes as $objectType) {
                if ((int) $objectType['id'] === $candidateID) {
                    $this->objectTypeConstant = $objectType['const'];
                    $this->objectTypeID = (int) $objectType['id'];
                    $this->objectTypeTitle = $objectType['title'];
                    return true;
                }
            }
        } else {
            foreach ($objectTypes as $objectType) {
                if (strtolower($objectType['title']) === strtolower($candidate)) {
                    $this->objectTypeConstant = $objectType['const'];
                    $this->objectTypeID = (int) $objectType['id'];
                    $this->objectTypeTitle = $objectType['title'];
                    return true;
                } elseif (strtolower($objectType['const']) === strtolower($candidate)) {
                    $this->objectTypeConstant = $objectType['const'];
                    $this->objectTypeID = (int) $objectType['id'];
                    $this->objectTypeTitle = $objectType['title'];
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Try to identify object
     *
     * If found set…
     * @see $objectID
     * @see $objectTitle
     * @see $object
     *
     * Also set object type if needed by using
     * @see identifyObjectType
     *
     * @param string $candidate Title or numeric identifier
     *
     * @return bool Returns true if found, otherwise false
     *
     * @throws \Exception on error
     */
    protected function identifyObject(string $candidate): bool {
        if (strlen($candidate) === 0) {
            return false;
        }

        $this->objectTitle = $candidate;

        if ($this->hasObjectType() && is_numeric($candidate) && (int) $candidate > 0) {
            $object = $this->useIdoitAPIFactory()->getCMDBObject()->read((int) $candidate);

            if (count($object) > 0) {
                $this->object = $object;
                $this->objectID = (int) $this->object['id'];
                $this->objectTitle = $this->object['title'];
                return true;
            }
        } elseif (!$this->hasObjectType() && is_numeric($candidate) && (int) $candidate > 0) {
            $object = $this->useIdoitAPIFactory()->getCMDBObject()->read((int) $candidate);

            if (count($object) > 0) {
                $this->object = $object;
                $this->objectID = (int) $this->object['id'];
                $this->objectTitle = $this->object['title'];

                $this->identifyObjectType((string) $this->object['type']);
                return true;
            }
        } elseif ($this->hasObjectType()) {
            $objects = $this->useIdoitAPI()->fetchObjects([
                'title' => $candidate,
                'type' => $this->objectTypeID
            ]);

            switch (count($objects)) {
                case 0:
                    return false;
                case 1:
                    $this->object = end($objects);
                    $this->objectID = (int) $this->object['id'];
                    $this->objectTitle = $this->object['title'];
                    return true;
                default:
                    throw new \RuntimeException(sprintf(
                        'Object title "%s" is ambiguous',
                        $candidate
                    ));
            }
        } else {
            $objects = $this->useIdoitAPI()->fetchObjects([
                'title' => $candidate
            ]);

            switch (count($objects)) {
                case 0:
                    return false;
                case 1:
                    $this->object = end($objects);
                    $this->objectID = (int) $this->object['id'];
                    $this->objectTitle = $this->object['title'];

                    $this->identifyObjectType((string) $this->object['type']);
                    return true;
                default:
                    throw new \RuntimeException(sprintf(
                        'Object title "%s" is ambiguous',
                        $candidate
                    ));
            }
        }

        return false;
    }

    /**
     * Try to identify category
     *
     * If found set…
     * @see $attributes
     * @see $categoryConstant
     * @see $categoryID
     * @see $categoryTitle
     *
     * @param string $candidate Localized name, constant or numeric identifier
     *
     * @return bool Returns true if found, otherwise \Exception is thrown
     *
     * @throws \Exception on error
     */
    protected function identifyCategory(string $candidate): bool {
        if (strlen($candidate) === 0 &&
            $this->useUserInteraction()->isInteractive()) {
            $candidate = $this->askForCategory();
        }

        $categories = $this->useCache()->getCategories();

        if (is_numeric($candidate) && (int) $candidate > 0) {
            $candidateID = (int) $candidate;

            foreach ($categories as $category) {
                if ((int) $category['id'] === $candidateID) {
                    $this->categoryAttributes = $category['properties'];
                    $this->categoryConstant = $category['const'];
                    $this->categoryID = (int) $category['id'];
                    $this->categoryTitle = $category['title'];
                    return true;
                }
            }
        } else {
            foreach ($categories as $category) {
                if (strtolower($category['title']) === strtolower($candidate)) {
                    $this->categoryAttributes = $category['properties'];
                    $this->categoryConstant = $category['const'];
                    $this->categoryID = (int) $category['id'];
                    $this->categoryTitle = $category['title'];
                    $this->multiValue = ($category['multi_value'] === '0') ? false : true;
                    return true;
                } elseif (strtolower($category['const']) === strtolower($candidate)) {
                    $this->categoryAttributes = $category['properties'];
                    $this->categoryConstant = $category['const'];
                    $this->categoryID = (int) $category['id'];
                    $this->categoryTitle = $category['title'];
                    $this->multiValue = ($category['multi_value'] === '0') ? false : true;
                    return true;
                }
            }
        }

        throw new \BadMethodCallException(sprintf(
            'Unknown category "%s"',
            $candidate
        ));
    }

    /**
     * Try to identify category entry
     *
     * @param string $candidate
     *
     * @return bool Returns true, otherwise false
     *
     * @throws \Exception on error
     */
    protected function identifyEntry(string $candidate): bool {
        if (!$this->hasObject()) {
            throw new \BadMethodCallException(
                'Unknown objects cannot have category entries'
            );
        }

        if (!$this->hasCategory()) {
            throw new \BadMethodCallException(
                'Unknown categories cannot have entries'
            );
        }

        if (strlen($candidate) === 0 &&
            $this->useUserInteraction()->isInteractive()) {
            $candidate = $this->askForEntry();

            if (strlen($candidate) === 0) {
                return false;
            }
        }

        if (!is_numeric($candidate) && (int) $candidate <= 0) {
            throw new \BadMethodCallException(sprintf(
                'Category entry "%s" is not a valid numeric identifier',
                $candidate
            ));
        }

        $this->entryID = (int) $candidate;

        $this->entry = $this->useIdoitAPIFactory()->getCMDBCategory()->readOneByID(
            $this->objectID,
            $this->categoryConstant,
            $this->entryID
        );

        return true;
    }

    protected function isObjectToType(array $object, $objectTypeID): bool {
        if (array_key_exists('type', $object) &&
            (int) $object['type'] === $objectTypeID) {
            return true;
        } elseif (array_key_exists('objecttype', $object) &&
            (int) $object['objecttype'] === $objectTypeID) {
            return true;
        }

        return false;
    }

    /**
     * Is category really assigned to object type?
     *
     * @param string $objectTypeConstant Object type constant
     * @param string $categoryConstant Category constant
     *
     * @return bool Returns true if found, otherwise false
     *
     * @throws \Exception on error
     */
    protected function isCategoryAssignedToObjectType(string $objectTypeConstant, string $categoryConstant): bool {
        $assignedCategories = $this->useCache()->getAssignedCategories($objectTypeConstant);

        $types = ['catg', 'cats', 'custom'];

        foreach ($types as $type) {
            if (!array_key_exists($type, $assignedCategories)) {
                continue;
            }

            foreach ($assignedCategories[$type] as $category) {
                if ($categoryConstant === $category['const']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Has query an object type?
     *
     * @return bool
     */
    protected function hasObjectType(): bool {
        return isset($this->objectTypeID);
    }

    /**
     * Has query an object?
     *
     * @return bool
     */
    protected function hasObject(): bool {
        return isset($this->objectID);
    }

    /**
     * Has query a category?
     *
     * @return bool
     */
    protected function hasCategory(): bool {
        return isset($this->categoryID);
    }

    /**
     * Has query an entry identifier?
     *
     * @return bool
     */
    protected function hasEntry(): bool {
        return isset($this->entryID);
    }

    /**
     * Has user passed attributes as options?
     *
     * @return bool
     */
    protected function hasAttributes(): bool {
        return count($this->collectedAttributes) > 0;
    }

    protected function hasTemplate(): bool {
        return count($this->template) > 0;
    }

    protected function isMultiValueCategory(): bool {
        return $this->multiValue;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception
     */
    protected function loadTemplate() {
        try {
            if (!array_key_exists('templates', $this->config) ||
                !is_array($this->config['templates'])) {
                return $this;
            }

            $template = [];

            if (array_key_exists(
                $this->objectTypeConstant,
                $this->config['templates']
            )) {
                $template = $this->config['templates'][$this->objectTypeConstant];
            } elseif (array_key_exists(
                strtolower($this->objectTypeConstant),
                $this->config['templates']
            )) {
                $template = $this->config['templates'][strtolower($this->objectTypeConstant)];
            } elseif (array_key_exists(
                $this->objectTypeID,
                $this->config['templates']
            )) {
                $template = $this->config['templates'][$this->objectTypeID];
            } elseif (array_key_exists(
                $this->objectTypeTitle,
                $this->config['templates']
            )) {
                $template = $this->config['templates'][$this->objectTypeTitle];
            } elseif (array_key_exists(
                strtolower($this->objectTypeTitle),
                $this->config['templates']
            )) {
                $template = $this->config['templates'][strtolower($this->objectTypeTitle)];
            }

            if (!is_array($template)) {
                throw new \DomainException(
                    'Invalid data type'
                );
            }

            if (count($template) === 0) {
                $this->log->warning(
                    'Empty template found for object type "%s" [%s]',
                    $this->objectTypeTitle,
                    $this->objectTypeConstant
                );

                return $this;
            }

            $this->log->debug(
                'Template found for object type "%s" [%s]',
                $this->objectTypeTitle,
                $this->objectTypeConstant
            );

            $this->template = [];

            $categories = $this->useCache()->getCategories();

            foreach ($template as $index => $block) {
                if (!is_array($block)) {
                    throw new \DomainException(sprintf(
                        'Block %s has wrong data type',
                        $index
                    ));
                }

                if (!array_key_exists('category', $block)) {
                    throw new \DomainException(sprintf(
                        'Block %s needs a category name, constant or numeric identifier',
                        $index
                    ));
                }

                if (!array_key_exists('attribute', $block)) {
                    throw new \DomainException(sprintf(
                        'Block %s needs an attribute key or name',
                        $index
                    ));
                }

                $categoryConstant = '';
                $categoryID = '';
                $categoryTitle = '';

                if (is_numeric($block['category']) && (int) $block['category'] > 0) {
                    $candidateID = (int) $block['category'];

                    foreach ($categories as $category) {
                        if ((int) $category['id'] === $candidateID) {
                            $categoryConstant = $category['const'];
                            $categoryID = (int) $category['id'];
                            $categoryTitle = $category['title'];
                            break;
                        }
                    }
                } else {
                    foreach ($categories as $category) {
                        if (strtolower($category['title']) === strtolower($block['category'])) {
                            $categoryConstant = $category['const'];
                            $categoryID = (int) $category['id'];
                            $categoryTitle = $category['title'];
                            break;
                        } elseif (strtolower($category['const']) === strtolower($block['category'])) {
                            $categoryConstant = $category['const'];
                            $categoryID = (int) $category['id'];
                            $categoryTitle = $category['title'];
                            break;
                        }
                    }
                }

                if (strlen($categoryConstant) === 0) {
                    throw new \DomainException(sprintf(
                        'Unknown category in block #%s',
                        $index
                    ));
                }

                $attributeKey = '';
                $attributeTitle = '';
                $attribute = [];

                $categoryInfo = $this->useCache()->getCategoryInfo($categoryConstant);

                foreach ($categoryInfo['properties'] as $categoryAttributeKey => $categoryAttribute) {
                    if ($categoryAttributeKey === $block['attribute']) {
                        $attributeKey = $categoryAttributeKey;
                        $attributeTitle = $categoryAttribute['title'];
                        $attribute = $categoryAttribute;
                        break;
                    }

                    if (strtolower($categoryAttribute['title']) === $block['attribute']) {
                        $attributeKey = $categoryAttributeKey;
                        $attributeTitle = $categoryAttribute['title'];
                        $attribute = $categoryAttribute;
                        break;
                    }
                }

                if (strlen($attributeKey) === 0) {
                    throw new \DomainException(sprintf(
                        'Unknown attribute for category "%s" [%s] in block #%s',
                        $categoryTitle,
                        $categoryConstant,
                        $index
                    ));
                }

                $data = [
                    'categoryConstant' => $categoryConstant,
                    'categoryTitle' => $categoryTitle,
                    'categoryID' => $categoryID,
                    'attributeKey' => $attributeKey,
                    'attributeTitle' => $attributeTitle,
                    'attribute' => $attribute
                ];

                if (array_key_exists('default', $block) &&
                    is_string($block['default']) &&
                    strlen($block['default']) > 0) {
                    $data['defaultValue'] = $block['default'];
                }

                $this->template[] = $data;
            }
        } catch (\Exception $e) {
            throw new \DomainException(sprintf(
                'Template for object type "%s" [%s] is invalid: %s',
                $this->objectTypeTitle,
                $this->objectTypeConstant,
                $e->getMessage()
            ));
        }

        return $this;
    }

    /**
     * Try to pre-load entries for single-value categories found in loaded template
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function preloadEntriesFromTemplate(): self {
        if (!$this->hasObject()) {
            return $this;
        }

        $categoryConstants = [];

        foreach ($this->template as $block) {
            if (in_array($block['categoryConstant'], $categoryConstants)) {
                continue;
            }

            $categoryInfo = $this->useCache()->getCategoryInfo($block['categoryConstant']);

            if ($categoryInfo['multi_value'] === '1') {
                continue;
            }

            $categoryConstants[] = $block['categoryConstant'];
        }

        if (count($categoryConstants) === 0) {
            return $this;
        }

        $result = $this->useIdoitAPIFactory()->getCMDBCategory()->batchRead(
            [$this->objectID],
            $categoryConstants
        );

        $entries = [];

        for ($index = 0; $index < count($categoryConstants); $index++) {
            if (!is_array($result[$index]) ||
                !array_key_exists(0, $result[$index]) ||
                !is_array($result[$index][0]) ||
                count($result[$index][0]) === 0) {
                continue;
            }

            $entries[$categoryConstants[$index]] = $result[$index][0];
        }

        unset($result);

        if (count($entries) === 0) {
            return $this;
        }

        foreach ($this->template as $index => $block) {
            if (!array_key_exists($block['categoryConstant'], $entries)) {
                continue;
            }

            if (!array_key_exists($block['attributeKey'], $entries[$block['categoryConstant']]) ||
                !isset($entries[$block['categoryConstant']][$block['attributeKey']])) {
                continue;
            }

            $attribute = (new Attribute($this->config, $this->log))
                ->setUp($block['attribute'], $this->useIdoitAPI(), $this->useIdoitAPIFactory());

            if ($attribute->ignore() || $attribute->isReadonly()) {
                continue;
            }

            $defaultValue = $attribute
                ->encode($entries[$block['categoryConstant']][$block['attributeKey']]);

            $this->template[$index]['defaultValue'] = $defaultValue;
        }



        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function applyTemplate() {
        foreach ($this->template as $index => $block) {
            $attribute = (new Attribute($this->config, $this->log))
                ->setUp($block['attribute'], $this->useIdoitAPI(), $this->useIdoitAPIFactory());

            if ($attribute->ignore() || $attribute->isReadonly()) {
                continue;
            }

            $value = $this->askForAttribute(
                $block['categoryTitle'],
                $block['attribute'],
                array_key_exists('defaultValue', $block) ? $block['defaultValue'] : ''
            );

            // Use default value if user skipped it:
            if (!isset($value) &&
                array_key_exists('defaultValue', $block)) {
                $value = $attribute
                    ->decode($block['defaultValue']);
            }

            if (isset($value)) {
                $this->template[$index]['value'] = $value;
            }
        }

        return $this;
    }

    /**
     * Save data
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function save() {
        if ($this->hasObject() && $this->hasAttributes() && $this->hasEntry() && !$this->hasTemplate()) {
            $this->log->info('Update 1 category entry');

            $this->useIdoitAPIFactory()->getCMDBCategory()->save(
                $this->objectID,
                $this->categoryConstant,
                $this->collectedAttributes,
                ($this->isMultiValueCategory()) ? $this->entryID : null
            );

            $this->log->info(
                'Link: %s?objID=%s',
                str_replace('src/jsonrpc.php', '', $this->config['api']['url']),
                $this->objectID
            );
        } elseif ($this->hasObject() && $this->hasAttributes() && !$this->hasEntry() && !$this->hasTemplate()) {
            $this->log->info('Create 1 category entry');

            $this->useIdoitAPIFactory()->getCMDBCategory()->save(
                $this->objectID,
                $this->categoryConstant,
                $this->collectedAttributes
            );

            $this->log->info(
                'Link: %s?objID=%s',
                str_replace('src/jsonrpc.php', '', $this->config['api']['url']),
                $this->objectID
            );
        } elseif (!$this->hasObject() && $this->hasAttributes() && !$this->hasTemplate()) {
            $this->log->info('Create new object and save 1 category entry');

            $result = $this->useIdoitAPIFactory()->getCMDBObject()->createWithCategories(
                $this->objectTypeConstant,
                $this->objectTitle,
                [
                    $this->categoryConstant => [$this->collectedAttributes]
                ]
            );

            $this->log->info(
                'Link: %s?objID=%s',
                str_replace('src/jsonrpc.php', '', $this->config['api']['url']),
                $result['id']
            );
        } elseif (!$this->hasObject() && !$this->hasAttributes() && !$this->hasTemplate()) {
            $this->log->info('Create object');

            $this->objectID = $this->useIdoitAPIFactory()->getCMDBObject()->create(
                $this->objectTypeConstant,
                $this->objectTitle
            );

            $this->log->info(
                'Link: %s?objID=%s',
                str_replace('src/jsonrpc.php', '', $this->config['api']['url']),
                $this->objectID
            );
        } elseif ($this->hasObject() && $this->hasTemplate() && !$this->hasAttributes()) {
            $categories = [];

            foreach ($this->template as $block) {
                if (!array_key_exists('value', $block)) {
                    continue;
                }

                if (!array_key_exists($block['categoryConstant'], $categories)) {
                    $categories[$block['categoryConstant']] = [];
                }

                $categories[$block['categoryConstant']][$block['attributeKey']] = $block['value'];
            }

            $requests = [];

            foreach ($categories as $categoryConstant => $attributes) {
                $requests[] = [
                    'method' => 'cmdb.category.save',
                    'params' => [
                        'object' => $this->objectID,
                        'category' => $categoryConstant,
                        'data' => $attributes
                    ]
                ];
            }

            switch (count($requests)) {
                case 0:
                    $this->log->notice('Nothing to do');
                    break;
                case 1:
                    $this->log->info('Save 1 category entry');

                    $this->useIdoitAPIFactory()->getAPI()->batchRequest($requests);
                    break;
                default:
                    $this->log->info(
                        'Save %s category entries',
                        count($requests)
                    );

                    $this->useIdoitAPIFactory()->getAPI()->batchRequest($requests);
                    break;
            }

            $this->log->info(
                'Link: %s?objID=%s',
                str_replace('src/jsonrpc.php', '', $this->config['api']['url']),
                $this->objectID
            );
        } elseif (!$this->hasObject() && $this->hasTemplate() && !$this->hasAttributes()) {
            $categories = [];

            foreach ($this->template as $block) {
                if (!array_key_exists('value', $block)) {
                    continue;
                }

                if (!array_key_exists($block['categoryConstant'], $categories)) {
                    $categories[$block['categoryConstant']] = [0 => []];
                }

                $categories[$block['categoryConstant']][0][$block['attributeKey']] = $block['value'];
            }

            switch (count($categories)) {
                case 0:
                    $this->log->notice('Create object');
                    break;
                case 1:
                    $this->log->info('Create object and save 1 category entry');
                    break;
                default:
                    $this->log->info(
                        'Create object and save %s category entries',
                        count($categories)
                    );
                    break;
            }

            $result = $this->useIdoitAPIFactory()->getCMDBObject()->createWithCategories(
                $this->objectTypeConstant,
                $this->objectTitle,
                $categories
            );

            $this->log->info(
                'Link: %s?objID=%s',
                str_replace('src/jsonrpc.php', '', $this->config['api']['url']),
                $result['id']
            );
        } else {
            $this->log->notice('Nothing to do');
        }

        return $this;
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
    \$ %1\$s %2\$s [OPTIONS] [QUERY]

<strong>ARGUMENTS</strong>
    QUERY   <dim>Combination of</dim> <u>type/object/category/entry</u>
    
            <u>type</u>     <dim>is the localized name of an object type,</dim>
                     <dim>its constant or its numeric identifier</dim>
            <u>object</u>   <dim>title or numeric identifier</dim>
            <u>category</u> <dim>is the localized name of the category,</dim>
                     <dim>its contant or numeric identifier</dim>
            <u>entry</u>    <dim>is the numeric identifier</dim>
                     <dim>of an existing category entry</dim>

<strong>COMMAND OPTIONS</strong>
    -a <u>ATTRIBUTE=VALUE</u>,         <dim>Localized name or key of ATTRIBUTE</dim>
    --attribute=<u>ATTRIBUTE=VALUE</u> <dim>and its VALUE</dim>
                                <dim>(Use only if category is set)</dim>

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
    <dim># Create new object with type "server" and title "mylittleserver":</dim>
    \$ %1\$s %2\$s server/mylittleserver

    <dim># Create/update attributes in a single-value category:</dim>
    \$ %1\$s %2\$s server/mylittleserver/model \\
        -a manufacturer=VendorA -a model=ModelA
    <dim># Another one:</dim>
    \$ %1\$s %2\$s server/mylittleserver/location \\
        -a location="Data Center"

    <dim># Update attributes in a multi-value category:</dim>
    \$ %1\$s %2\$s server/mylittleserver/host\\ address/1 \\
        -a ipv4_address=192.168.42.23 \\
        -a hostname=mylittleserver \\
        -a domain=example.com

    <dim># Use a template for an interactive mode:</dim>
    \$ cat template.json
    {
        "templates": {
            "server": [
                {
                    "category": "model",
                    "attribute": "manufacturer"
                },
                {
                    "category": "model",
                    "attribute": "model"
                },
                {
                    "category": "host address",
                    "attribute": "ipv4_address"
                },
                {
                    "category": "host address",
                    "attribute": "hostname"
                },
                {
                    "category": "host address",
                    "attribute": "domain"
                },
                {
                    "category": "location",
                    "attribute": "location",
                    "default": "Root location"
                }
            ]
        }
    }
    <dim># Notes:</dim>
    <dim># - "category" (required): localized name of the category, its contant or</dim>
    <dim>#   numeric identifier</dim>
    <dim># - "attribute" (required): localized name of attribute or its key</dim>
    <dim># - "default" (optional): default value (must be a string)</dim>

    <dim># Interactive mode:</dim>
    \$ %1\$s %2\$s -c template.json
    %3\$s
    Type? server
    Title? mylittleserver
    Add more attributes? [Y/n] y
    [Model] Manufacturer? VendorA
    [Model] Model? ModelA
    [Host address] IPv4 address? 192.168.42.23
    [Host address] Hostname? mylittleserver
    [Host address] Domain? example.com
    [Location] Location? Data Center
    Link: http://cmdb.example.com/i-doit/?objID=42
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription()
        );

        return $this;
    }

}
