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
     * @return array{exitCode: int, stdout: string, stderr: string}
     *
     * @throws RuntimeException When the process cannot be started.
     */
    public static function run(string $script, array $arguments = []): array
    {
        $command = [PHP_BINARY];
        if (extension_loaded('xdebug')) {
            array_push($command, '-d', 'xdebug.mode=off');
        }
        array_push($command, $script, ...$arguments);

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException("Unable to start a PHP process for {$script}.");
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exitCode' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
