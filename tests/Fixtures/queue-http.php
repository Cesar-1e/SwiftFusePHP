<?php

/**
 * Router fixture for PHP's built-in server: dispatch jobs with the "deferred" driver.
 *
 * The directory comes from the SWIFTFUSE_QUEUE_PROBE environment variable, and
 * holds jobs/, queue.log and the "marker" file. With ?finish=1 the script defines
 * fastcgi_finish_request(), which the built-in server lacks, so the deferred
 * shutdown path runs; the stand-in records "finish" in the marker. The request
 * dispatches an "ok", a "throw" and an "echo" job, prints "response" and ends
 * with exit, as Controller::json() does.
 */

declare(strict_types=1);

use SwiftFuse\Queue\QueueManager;
use SwiftFuse\Support\Config;
use SwiftFuse\Tests\Fixtures\QueueProbeJob;

require dirname(__DIR__) . '/bootstrap.php';

$directory = (string) getenv('SWIFTFUSE_QUEUE_PROBE');
$marker = $directory . '/marker';

if (($_GET['finish'] ?? '0') === '1' && !function_exists('fastcgi_finish_request')) {
    /**
     * Stand-in for PHP-FPM's fastcgi_finish_request() in the built-in server.
     *
     * @return bool
     */
    function fastcgi_finish_request(): bool
    {
        file_put_contents(getenv('SWIFTFUSE_QUEUE_PROBE') . '/marker', 'finish' . PHP_EOL, FILE_APPEND | LOCK_EX);
        return true;
    }
}

Config::set('queue.path', $directory . '/jobs');
Config::set('queue.log', $directory . '/queue.log');

$queue = new QueueManager('deferred');
$queue->dispatch(new QueueProbeJob($marker, 'first'));
$queue->dispatch(new QueueProbeJob($marker, 'second', 0.0, 'throw'));
$queue->dispatch(new QueueProbeJob($marker, 'third', 0.0, 'echo'));

echo 'response';
exit;
