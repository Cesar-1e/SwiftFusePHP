<?php

/**
 * Subprocess fixture: drive the queue from a separate process.
 *
 * Usage: php queue-probe.php <jobs-dir> <log-file> <command> [arguments]
 *
 *   work                              Run Worker::work() and print the count.
 *   run <file>                        Run Worker::runFile() and print 1 or 0.
 *   dispatch <driver> <marker> [mode] Dispatch a QueueProbeJob and print its path.
 *   fuse <arguments...>               Run the console kernel ("fuse") and exit with its code.
 */

declare(strict_types=1);

use SwiftFuse\Console\Kernel;
use SwiftFuse\Queue\QueueManager;
use SwiftFuse\Queue\Worker;
use SwiftFuse\Support\Config;
use SwiftFuse\Tests\Fixtures\QueueProbeJob;

require dirname(__DIR__) . '/bootstrap.php';

[, $jobsPath, $logFile, $command] = $argv + [null, '', '', ''];
$arguments = array_slice($argv, 4);

Config::set('queue.path', $jobsPath);
Config::set('queue.log', $logFile);

switch ($command) {
    case 'work':
        echo (new Worker(new QueueManager()))->work();
        exit(0);

    case 'run':
        echo (new Worker(new QueueManager()))->runFile($arguments[0] ?? '') ? '1' : '0';
        exit(0);

    case 'dispatch':
        $queue = new QueueManager($arguments[0] ?? 'file');
        echo $queue->dispatch(new QueueProbeJob($arguments[1] ?? '', 'dispatched', 0.0, $arguments[2] ?? 'ok'));
        exit(0);

    case 'fuse':
        exit((new Kernel())->handle(['fuse', ...$arguments]));
}

fwrite(STDERR, "Unknown command: {$command}" . PHP_EOL);
exit(2);
