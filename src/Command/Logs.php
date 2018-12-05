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
 * Command "logs"
 */
class Logs extends Command {

    protected $filterByIDs = [];
    protected $filterByTitles = [];
    protected $filterByDateTime = '';
    protected $limit = 0;
    protected $hardLimit = 10000;

    protected $events = [
        'C__LOGBOOK_EVENT__OBJECTTYPE_PURGED' => 'Object type purged',
        'C__LOGBOOK_EVENT__OBJECTTYPE_ARCHIVED' => 'Object type archived',
        'C__LOGBOOK_EVENT__OBJECTTYPE_RECYCLED' => 'Object type recycled',
        'C__LOGBOOK_EVENT__OBJECTTYPE_CHANGED' => 'Object type updated',
        'C__LOGBOOK_EVENT__OBJECTTYPE_CREATED' => 'Object type created',
        'C__LOGBOOK_EVENT__OBJECT_PURGED' => 'Object purged',
        'C__LOGBOOK_EVENT__OBJECT_ARCHIVED' => 'Object archived',
        'C__LOGBOOK_EVENT__OBJECT_RECYCLED' => 'Object recycled',
        'C__LOGBOOK_EVENT__OBJECT_CHANGED' => 'Object updated',
        'C__LOGBOOK_EVENT__OBJECT_CREATED' => 'Object created',
        'C__LOGBOOK_EVENT__OBJECTTYPE_PURGED__NOT' => 'Object type purged… not',
        'C__LOGBOOK_EVENT__OBJECTTYPE_ARCHIVED__NOT' => 'Object type archived… not',
        'C__LOGBOOK_EVENT__OBJECTTYPE_RECYCLED__NOT' => 'Object type recycled… not',
        'C__LOGBOOK_EVENT__OBJECTTYPE_CHANGED__NOT' => 'Object type updated… not',
        'C__LOGBOOK_EVENT__OBJECTTYPE_CREATED__NOT' => 'Object type created… not',
        'C__LOGBOOK_EVENT__OBJECT_PURGED__NOT' => 'Object purged… not',
        'C__LOGBOOK_EVENT__OBJECT_ARCHIVED__NOT' => 'Object archived… not',
        'C__LOGBOOK_EVENT__OBJECT_RECYCLED__NOT' => 'Object recycled… not',
        'C__LOGBOOK_EVENT__OBJECT_CHANGED__NOT' => 'Object updated… not',
        'C__LOGBOOK_EVENT__OBJECT_CREATED__NOT' => 'Object created… not',
        // @todo What are these events?
//        'C__LOGBOOK_EVENT__POBJECT_FEMALE_SOCKET_CREATED__NOT' => '',
//        'C__LOGBOOK_EVENT__POBJECT_MALE_PLUG_CREATED__NOT' => '',
        'C__LOGBOOK_EVENT__CATEGORY_PURGED' => 'Category entry purged',
        'C__LOGBOOK_EVENT__CATEGORY_CHANGED' => 'Category entry updated',
        'C__LOGBOOK_EVENT__CATEGORY_ARCHIVED' => 'Category entry archvied',
        'C__LOGBOOK_EVENT__CATEGORY_DELETED' => 'Category entry deleted'
    ];

    protected $logs = [];

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

        $this
            ->buildFilters($this->config['options'])
            ->readLogs()
            ->printLogs();

        if ($this->follow()) {
            $this->keepFollowing();
        }

        return $this;
    }

    /**
     * @param array $options
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function buildFilters(array $options): self {
        return $this
            ->buildIDFilter($options)
            ->buildTitleFilter($options)
            ->buildLimitFilter($options)
            ->buildDateTimeFilter($options);
    }

    /**
     * @param array $options
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function buildIDFilter(array $options): self {
        if (!array_key_exists('id', $options)) {
            return $this;
        }

        $ids = [];

        if (is_array($options['id'])) {
            $ids = $options['id'];
        } elseif (is_string($options['id'])) {
            $ids[] = $options['id'];
        }

        foreach ($ids as $id) {
            if ($this->useValidate()->isIDAsString($id) === false) {
                throw new \BadMethodCallException(sprintf(
                    'Invalid filter by id: %s',
                    $id
                ));
            }

            $this->filterByIDs[] = (int) $id;
        }

        return $this;
    }

    /**
     * @param array $options
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function buildTitleFilter(array $options): self {
        if (!array_key_exists('title', $options)) {
            return $this;
        }

        $titles = [];

        if (is_array($options['title'])) {
            $titles = $options['title'];
        } elseif (is_string($options['title'])) {
            $titles[] = $options['title'];
        }

        foreach ($titles as $title) {
            if ($this->useValidate()->isOneLiner($title) === false) {
                throw new \BadMethodCallException(sprintf(
                    'Invalid filter by title: %s',
                    $title
                ));
            }

            $this->filterByTitles[] = $title;
        }

        return $this;
    }

    /**
     * @param array $options
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function buildLimitFilter(array $options): self {
        if (array_key_exists('n', $options)) {
            if (is_array($options['n'])) {
                throw new \BadMethodCallException(
                    'Use option -n only once'
                );
            }

            if (!is_numeric($options['n']) ||
                (int) $options['n'] <= 0) {
                throw new \BadMethodCallException(
                    'Option -n is not a positive integer'
                );
            }

            $this->limit = (int) $options['n'];
        }

        if (array_key_exists('number', $options)) {
            if ($this->limit > 0) {
                throw new \BadMethodCallException(
                    'Use either option -n or --number, not both'
                );
            }

            if (is_array($options['number'])) {
                throw new \BadMethodCallException(
                    'Use option --number only once'
                );
            }

            if (!is_numeric($options['number']) ||
                (int) $options['number'] <= 0) {
                throw new \BadMethodCallException(
                    'Option --number is not a positive integer'
                );
            }

            $this->limit = (int) $options['number'];
        }

        return $this;
    }

    /**
     * @param array $options
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function buildDateTimeFilter(array $options): self {
        if (!array_key_exists('since', $options)) {
            return $this;
        }

        if (is_array($options['since'])) {
            throw new \BadMethodCallException(
                'Use option --since only once'
            );
        }

        if (strtotime($options['since']) === false) {
            throw new \BadMethodCallException(
                'Option --since is not a valid date/time'
            );
        }

        $this->filterByDateTime = $options['since'];

        return $this;
    }

    protected function hasIDFilter(): bool {
        return count($this->filterByIDs) > 0;
    }

    protected function hasTitleFilter(): bool {
        return count($this->filterByTitles) > 0;
    }

    protected function hasDateTimeFilter(): bool {
        return strlen($this->filterByDateTime) > 0;
    }

    protected function hasAnyFilter(): bool {
        return $this->hasIDFilter() ||
            $this->hasTitleFilter() ||
            $this->hasDateTimeFilter();
    }

    protected function isLimited(): bool {
        return $this->limit > 0;
    }

    protected function follow(): bool {
        if (array_key_exists('f', $this->config['options']) ||
            array_key_exists('follow', $this->config['options'])) {
            return true;
        }

        return false;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function readLogs(): self {
        $this->logs = [];

        if (!$this->hasAnyFilter()) {
            $this->logs = $this->useIdoitAPIFactory()->getCMDBLogbook()->read(null, $this->hardLimit);
        } else {
            $since = null;

            if ($this->hasDateTimeFilter()) {
                $since = $this->filterByDateTime;
            }

            if ($this->hasIDFilter()) {
                foreach ($this->filterByIDs as $objectID) {
                    $this->logs = array_merge(
                        $this->logs,
                        $this->useIdoitAPIFactory()->getCMDBLogbook()->readByObject(
                            $objectID,
                            $since,
                            $this->hardLimit
                        )
                    );
                }

                usort(
                    $this->logs,
                    [self::class, 'sortLogsByDate']
                );
            } elseif ($this->hasTitleFilter()) {
                // @todo Fetch object IDs by titles, fetch log events and combine them!
            } else {
                $this->logs = $this->useIdoitAPIFactory()->getCMDBLogbook()->read(
                    $since,
                    $this->hardLimit
                );
            }

            if ($this->hasTitleFilter()) {
                $titles = $this->filterByTitles;

                $this->logs = array_filter(
                    $this->logs,
                    function ($log) use ($titles) {
                        foreach ($titles as $title) {
                            if (fnmatch($title, $log['object_title'], FNM_CASEFOLD)) {
                                return true;
                            }
                        }

                        return false;
                    }
                );
            }
        }

        if ($this->isLimited()) {
            $this->logs = array_slice(
                $this->logs,
                -$this->limit
            );
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function printLogs(): self {
        switch (count($this->logs)) {
            case 0:
                $this->log->printAsMessage()->debug(
                    'No log events'
                );
                break;
            case 1:
                $this->log->printAsMessage()->debug(
                    '1 log event:'
                );
                break;
            default:
                $this->log->printAsMessage()->debug(
                    '%s log events:',
                    count($this->logs)
                );
                break;
        }

        foreach ($this->logs as $log) {
            $this->printLog($log);
        }

        return $this;
    }

    /**
     * Sort logs by date
     *
     * @param array $logA
     * @param array $logB
     *
     * @return int
     */
    public static function sortLogsByDate(array $logA, $logB): int {
        if ($logA['date'] === $logB['date']) {
            return 0;
        }

        $logATimeStamp = strtotime($logA['date']);
        $logBTimeStamp = strtotime($logB['date']);

        return $logATimeStamp < $logBTimeStamp ? -1 : 1;
    }

    protected function printLog(array $log) {
        $id = $log['logbook_id'];
        $timestamp = $log['date'];
        $username = $log['username'];
        $objectTitle = $log['object_title'];
        $objectID = $log['object_id'];

        if (array_key_exists($log['event'], $this->events)) {
            $event = $this->events[$log['event']];
        } else {
            $event = $log['event'];
        }

        $this->log
            ->printAsOutput()
            ->info(
                <<< EOF
<dim>#$id</dim> <green>$username</green> @ <yellow>$timestamp</yellow>
<strong>$objectTitle</strong> [$objectID]
$event

EOF
            );
    }

    /**
     * @throws \Exception on error
     */
    protected function keepFollowing() {
        $this->filterByDateTime = date('c');

        if (count($this->logs) > 0) {
            $lastLog = end($this->logs);
            $timestamp = strtotime($lastLog['date']) + 1;
            $this->filterByDateTime = date('c', $timestamp);
        }

        while (true) {
            $this
                ->readLogs()
                ->printLogs();

            if (count($this->logs) > 0) {
                $lastLog = end($this->logs);
                $timestamp = strtotime($lastLog['date']) + 1;
                $this->filterByDateTime = date('c', $timestamp);
            }

            sleep(2);
        }
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
    \$ %1\$s %2\$s [OPTIONS]

<strong>COMMAND OPTIONS</strong>
    -f, --follow        <dim>Start a continuous stream of logs</dim>
    --id=<u>ID</u>             <dim>Filter logs by numeric object identifier</dim>
                        <dim>Repeat to filter by more than one identifiers</dim>
    -n <u>LIMIT</u>,           <dim>Limit to last number of logs</dim>
    --number=<u>LIMIT</u>      <dim>Note: There is a hard limit set to a maximum amount of</dim>
                        <dim>%4\$s entries to prevent server-side errors</dim>
    --since=<u>TIME</u>        <dim>Filter logs since a specific date/time</dim>
                        <dim>May be everything that can be interpreted as a date/time</dim>
    --title=<u>TITLE</u>       <dim>Filter logs by object title</dim>
                        <dim>Wildcards like "*" and "[ae]" are allowed</dim>
                        <dim>Repeat to filter by more than one titles</dim>
    
    <dim>Any combination of options is allowed.</dim>

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
    <dim># Read all logs at once:</dim>
    \$ %1\$s %2\$s

    <dim># Read logs about 2 objects by their identifiers:</dim>
    \$ %1\$s %2\$s --id 23 --id 42

    <dim># Read logs about various objects by similar titles:</dim>
    \$ %1\$s %2\$s --title "host*.example.com"

    <dim># Follow:</dim>
    \$ %1\$s %2\$s -f

    <dim># Print only logs since today:</dim>
    \$ %1\$s %2\$s --since today

    <dim># Or since a specific date:</dim>
    \$ %1\$s %2\$s --since 2018-01-01
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription(),
            $this->hardLimit
        );

        return $this;
    }

}
