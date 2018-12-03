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
 * Command "log"
 */
class Log extends Command {

    protected $objectID;
    protected $objectTitle;
    protected $message;

    /**
     * Execute command
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    public function execute(): self {
        $this->log->info($this->getDescription());

        switch (count($this->config['arguments'])) {#
            case 0:
                break;
            case 1:
                $this->identifyObject($this->config['arguments'][0]);
                break;
            default:
                throw new \BadMethodCallException(
                    'Too many arguments; please provide only one object title or numeric identifier'
                );
        }

        $this->identifyMessage($this->config['options']);

        if (!$this->hasObject()) {
            if ($this->userInteraction->isInteractive() === false) {
                throw new \BadMethodCallException(
                    'No object, no log'
                );
            }

            $this->askForObject();
        }

        $this->reportObject();

        if (!$this->hasMessage()) {
            if ($this->userInteraction->isInteractive() === false) {
                throw new \BadMethodCallException(
                    'No message, no log'
                );
            }

            $this->askForMessage();
        }

        $this->reportMessage();

        $this->save();

        return $this;
    }

    /**
     * @param string $candidate
     *
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function identifyObject(string $candidate): self {
        if (is_numeric($candidate) && (int) $candidate > 0) {
            $object = $this->useIdoitAPI()->getCMDBObject()->read((int) $candidate);

            if (count($object) > 0) {
                $this->objectID = (int) $object['id'];
                $this->objectTitle = $object['title'];
            } else {
                throw new \BadMethodCallException(sprintf(
                    'Object not found by numeric identifier %s',
                    $candidate
                ));
            }
        } else {
            $objects = $this->useIdoitAPI()->fetchObjects([
                'title' => $candidate
            ]);

            switch (count($objects)) {
                case 0:
                    throw new \BadMethodCallException(sprintf(
                        'Object not found by title "%s"',
                        $candidate
                    ));
                case 1:
                    $object = end($objects);
                    $this->objectID = (int) $object['id'];
                    $this->objectTitle = $object['title'];
                    break;
                default:
                    throw new \RuntimeException(sprintf(
                        'Object title "%s" is ambiguous',
                        $candidate
                    ));
            }
        }

        return $this;
    }

    protected function identifyMessage(array $options): self {
        $message = '';

        if (array_key_exists('m', $options)) {
            if (is_array($options['m'])) {
                throw new \BadMethodCallException(
                    'Use option -m only once'
                );
            }

            $message = $options['m'];
        }

        if (array_key_exists('message', $options)) {
            if (isset($message)) {
                throw new \BadMethodCallException(
                    'Use either option -m or --message, not both'
                );
            }

            if (is_array($options['message'])) {
                throw new \BadMethodCallException(
                    'Use option --message only once'
                );
            }

            $message = $options['message'];
        }

        if (strlen($message) > 0) {
            $this->message = $message;
        }

        return $this;
    }

    protected function hasObject(): bool {
        return isset($this->objectID);
    }

    protected function hasMessage(): bool {
        return isset($this->message);
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function askForObject(): self {
        $object = $this->userInteraction->askQuestion('Object?');

        if (strlen($object) === 0) {
            $this->log->warning('Please re-try');
            return $this->askForObject();
        }

        try {
            $this->identifyObject($object);
        } catch (\BadMethodCallException $e) {
            $this->log->warning($e->getMessage());
            $this->log->warning('Please re-try');
            return $this->askForObject();
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function askForMessage(): self {
        try {
            if (function_exists('exec') === false) {
                throw new \RuntimeException(
                    'Executing external commands via shell is not allowed; probably function "exec" is disabled'
                );
            }

            $editor = $this->findEditor();

            if ($editor === '') {
                // Last chance:
                $editor = $this->askForEditor();
            }

            $this->log->debug('Editor: %s', $editor);

            $tmpFile = $temp_file = tempnam(
                sys_get_temp_dir(),
                $this->config['composer']['extra']['name'] . '_'
            );

            if (!is_writable($tmpFile)) {
                throw new \RuntimeException(sprintf(
                    'Permission denied to write file "%s"',
                    $tmpFile
                ));
            }

            $objectTitle = $this->objectTitle;
            $objectID = $this->objectID;

            $status = file_put_contents(
                $tmpFile,
                <<<EOF

# Please provide a message text for object:
#     $objectTitle [$objectID]
#
# Lines beginning with "#" will be treated as comments
# and will be ignored in the message text.
EOF
            );

            if ($status === false) {
                throw new \RuntimeException(sprintf(
                    'Unable to write to temporary file "%s"',
                    $tmpFile
                ));
            }

            $exitCode = 0;
            $output = [];

            exec("$editor $tmpFile > `tty`", $output, $exitCode);

            if ($exitCode > 0) {
                foreach ($output as $line) {
                    $this->log->warning($line);
                }

                throw new \RuntimeException(sprintf(
                    'Editor closed with exit code %s',
                    $exitCode
                ));
            }

            $content = file_get_contents($tmpFile);

            if ($content === false) {
                throw new \RuntimeException(sprintf(
                    'Unable to read from temporary file "%s"',
                    $tmpFile
                ));
            }

            $status = unlink($tmpFile);

            if ($status === false) {
                throw new \RuntimeException(sprintf(
                    'Unable to remove temporary file "%s"',
                    $tmpFile
                ));
            }

            $lines = explode(PHP_EOL, $content);

            $messageLines = [];

            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) {
                    continue;
                }

                $messageLines[] = $line;
            }

            $this->message = trim(implode(PHP_EOL, $messageLines));

            if (strlen($this->message) === 0) {
                throw new \RuntimeException(
                    'Your message is empty'
                );
            }
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Asking for a message resulted in an error: %s',
                $e->getMessage()
            ));
        }

        return $this;
    }

    /**
     * Find a proper editor to edit files
     *
     * Try (in the following order):
     * - Environment variable "EDITOR"
     * - Environment variable "VISUAL"
     * - Command "editor"
     * - Command "sensible-editor"
     * - Command "xdg-mime query default"
     * - Editor "nano"
     * - Editor "vim"
     * - Editor "vi"
     * - Editor "joe"
     * - Editor "gedit"
     * - Editor "kate"
     *
     * @return string
     */
    protected function findEditor(): string {
        $environmentVariables = [
            'EDITOR',
            'VISUAL'
        ];

        foreach ($environmentVariables as $environmentVariable) {
            $this->log->debug('Check environment variable "%s"', $environmentVariable);

            $editor = getenv($environmentVariable);

            if ($editor !== false && strlen($editor) > 0) {
                return $editor;
            }
        }

        $commands = [
            'editor',
            'sensible-editor',
            'xdg-mime query default',
            'nano',
            'vim',
            'vi',
            'joe',
            'gedit',
            'kate'
        ];

        foreach ($commands as $command) {
            $this->log->debug('Check command "%s"', $command);

            if ($this->testCommand($command)) {
                return $command;
            }
        }

        $this->log->debug('No editor found');

        return '';
    }

    protected function testCommand(string $command): bool {
        $output = [];
        $exitCode = 0;

        exec("type $command > /dev/null 2> /dev/null", $output, $exitCode);

        if ($exitCode === 0) {
            return true;
        }

        return false;
    }

    protected function askForEditor(): string {
        $editor = $this->userInteraction->askQuestion('Which editor do you prefer?');

        if (strlen($editor) === 0) {
            $this->log->warning('Excuse me, what do you mean?');

            return $this->askForEditor();
        }

        if ($this->testCommand($editor) === false) {
            $this->log->warning('Command not found');

            return $this->askForEditor();
        }

        return $editor;
    }

    protected function reportObject(): self {
        $this->log->debug(
            'Object identified: %s [%s]',
            $this->objectTitle,
            $this->objectID
        );

        return $this;
    }

    protected function reportMessage(): self {
        $this->log->debug(
            'Message:',
            $this->message
        );

        $lines = explode(PHP_EOL, $this->message);

        foreach ($lines as $line) {
            $this->log->debug($line);
        }

        return $this;
    }

    /**
     * @return self Returns itself
     *
     * @throws \Exception on error
     */
    protected function save(): self {
        $this->log->debug('Save…');

        $this->useIdoitAPI()->getCMDBLogbook()->create(
            $this->objectID,
            $this->message
        );

        return $this;
    }

    /**
     * Print usage of command
     *
     * @return self Returns itself
     */
    public function printUsage(): self {
        $editor = $this->findEditor();

        if ($editor === '') {
            $editor = 'no editor found';
        }

        $this->log->info(
            <<< EOF
%3\$s

<strong>USAGE</strong>
    \$ %1\$s %2\$s [OPTIONS] [OBJECT]
    
<strong>ARGUMENTS</strong>
    OBJECT              <dim>Object title or numeric identifier</dim>

<strong>COMMAND OPTIONS</strong>
    -m <u>MESSAGE</u>,         <dim>Add message text, otherwise type your message in</dim>
    --message=<u>MESSAGE</u>   <dim>your prefered editor: %4\$s</dim>

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
    <dim># Add entry to object identified by its title:</dim>
    \$ %1\$s %2\$s host01.example.com -m "Reboot server for Kernel updates"

    <dim># …or by its numeric identifier:</dim>
    \$ %1\$s %2\$s 42 -m "Reboot server for Kernel updates"

    <dim># If object or message is omitted you'll be asked for it:</dim>
    \$ %1\$s %2\$s
    Object? host01.example.com
    <dim># Based on environment variable EDITOR</dim>
    <dim># type your message in your prefered editor…</dim>
EOF
            ,
            $this->config['composer']['extra']['name'],
            $this->getName(),
            $this->getDescription(),
            $editor
        );

        return $this;
    }

}
