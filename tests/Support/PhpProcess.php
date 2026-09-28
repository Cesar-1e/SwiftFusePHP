<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Support;

use RuntimeException;

/**
 * Runs PHP scripts in separate processes for the tests.
 *
 * Needed for code that ends the process, such as Controller::json(), for code
 * that must boot with another project root, and to isolate test scripts from
 * each other's static state.
 */
final class PhpProcess
{
    /**
     * Run a PHP script in a new process and capture its output.
     *
     * The debugger extension is switched off in the child so its connection
     * notices never mix with the captured output.
     *
     * @param string $script Absolute path of the script to run.
     * @param array<int, string> $arguments Arguments passed to the script.
     * @param array<string, string> $ini php.ini settings for the child, passed with -d.
     * @return array{exitCode: int, stdout: string, stderr: string}
     *
     * @throws RuntimeException When the process cannot be started.
     */
    public static function run(string $script, array $arguments = [], array $ini = []): array
    {
        return self::wait(self::start($script, $arguments, $ini));
    }

    /**
     * Start a PHP script in a new process without waiting for it.
     *
     * @param string $script Absolute path of the script to run.
     * @param array<int, string> $arguments Arguments passed to the script.
     * @param array<string, string> $ini php.ini settings for the child, passed with -d.
     * @return array{process: resource, pipes: array<int, resource>} Handle for wait().
     *
     * @throws RuntimeException When the process cannot be started.
     */
    public static function start(string $script, array $arguments = [], array $ini = []): array
    {
        $command = [PHP_BINARY];
        if (extension_loaded('xdebug')) {
            $ini += ['xdebug.mode' => 'off'];
        }
        foreach ($ini as $name => $value) {
            array_push($command, '-d', "{$name}={$value}");
        }
        array_push($command, $script, ...$arguments);

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException("Unable to start a PHP process for {$script}.");
        }

        return ['process' => $process, 'pipes' => $pipes];
    }

    /**
     * Wait for a process started with start() and capture its output.
     *
     * @param array{process: resource, pipes: array<int, resource>} $started Handle from start().
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public static function wait(array $started): array
    {
        $stdout = (string) stream_get_contents($started['pipes'][1]);
        $stderr = (string) stream_get_contents($started['pipes'][2]);
        fclose($started['pipes'][1]);
        fclose($started['pipes'][2]);

        return ['exitCode' => proc_close($started['process']), 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
