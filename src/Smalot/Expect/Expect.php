<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2015 Sebastien MALOT
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Smalot\Expect;

/**
 * Class Expect
 * @package Smalot\Expect
 */
class Expect
{
    const EXP_EOL = 'eol';
    const EXP_TIMEOUT = 'timeout';

    const EXP_REGEXP = 'regexp';
    const EXP_GLOB = 'glob';
    const EXP_EXACT = 'exact';

    /**
     * @var int
     */
    protected $defaultTimeout;

    /**
     * @var array
     */
    protected $pipes;

    /**
     * @var resource
     */
    protected $process;

    /**
     * Constructor.
     */
    public function __construct($timeout = 3000)
    {
        $this->defaultTimeout = $timeout;
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param int $timeout
     * @return int
     *   Timeout.
     */
    protected function getTimeout($timeout)
    {
        return is_null($timeout) ? $this->defaultTimeout : $timeout;
    }

    /**
     * Open and start process.
     *
     * @param string $command
     * @param string $cwd
     * @param array $env
     * @param string $err
     *
     * @return $this
     * @throws \Exception
     */
    public function open($command, $cwd = null, $env = null, $err = '/dev/null')
    {
        if (null === $cwd) {
            $cwd = sys_get_temp_dir();
        }

        $descriptorspec = array(
          0 => array('pipe', 'r'),  // stdin is a pipe that the child will read from
          1 => array('pipe', 'w'),  // stdout is a pipe that the child will write to
          2 => array('file', $err, 'a') // stderr is a file to write to
        );

        $this->process = proc_open($command, $descriptorspec, $this->pipes, $cwd, $env);

        if (!is_resource($this->process)) {
            unset($this->process);
            unset($this->pipes);

            throw new \Exception('Failed to open process.');
        }

        // Non blocking mode for standard input.
        stream_set_blocking($this->pipes[1], 0);

        return $this;
    }

    /**
     * Close process and clear properties.
     *
     * @return $this
     */
    public function close()
    {
        if (is_resource($this->process)) {
            fclose($this->pipes[0]);
            fclose($this->pipes[1]);
            fclose($this->pipes[2]);
            unset($this->pipes);
            unset($this->process);
        }

        return $this;
    }

    /**
     * Indicate if process is still running.
     *
     * @return bool
     *   TRUE: still running.
     */
    public function isRunning()
    {
        if (!is_resource($this->process)) {
            return false;
        }

        $stat = proc_get_status($this->process);

        return ($stat['running'] > 0);
    }

    /**
     * @param $command
     * @param bool $new_line
     * @return $this
     * @throws \Exception
     */
    public function write($command, $new_line = true)
    {
        if (!$this->isRunning()) {
            throw new \Exception('Process not running.');
        }

        fwrite($this->pipes[0], $command.($new_line ? PHP_EOL : ''));

        return $this;
    }

    /**
     * @param array $cases
     * @param array $match
     *   Buffer.
     * @param int $timeout
     *   Timeout in milliseconds.
     *
     * @return string
     *   Case token.
     *
     * @throws \Exception
     */
    public function expect($cases, &$match = array(), $timeout = null)
    {
        $start = microtime(true);
        $timeout = $this->getTimeout($timeout);
        $buffer = '';

        do {
            // Check if time is over.
            if ($timeout > 0 && (((microtime(true) - $start) * 1000) > $timeout)) {
                // Returns the current buffer.
                $match = array($buffer);

                return self::EXP_TIMEOUT;
            }

            // Retrieve new char from standard input.
            $char = stream_get_contents($this->pipes[1], 1);

            if ($char !== '') {
                $buffer .= $char;

                // Look for matching case.
                if ($case = $this->checkCases($cases, $match, $buffer)) {
                    return $case;
                }
            } else {
                // Process is down.
                if (!$this->isRunning()) {
                    $match = array($buffer);

                    return self::EXP_EOL;
                }

                // Wait 0.5 ms for the next char.
                // Avoid cpu overload.
                usleep(500);
                echo '+';
            }
        } while (true);
    }

    /**
     * @param array $cases
     * @param array $match
     * @param string $buffer
     * @return bool|string
     */
    protected function checkCases($cases, &$match, $buffer)
    {
        foreach ($cases as $case => $condition) {
            // Default is GLOB type.
            $type = isset($condition[1]) ? $condition[1] : '';

            if ($type == self::EXP_EXACT && $condition[0] == $buffer) {
                $match = array($buffer);

                return $case;
            } elseif ($type == self::EXP_REGEXP && preg_match($condition[0], $buffer, $match)) {
                return $case;
            } elseif (strpos($buffer, $condition[0]) !== false) {
                $match = array($buffer);

                return $case;
            }
        }

        return false;
    }
}
