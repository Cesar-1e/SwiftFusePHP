<?php

/**
 * Queue: exclusive job reservation, interrupted jobs, the async fallback without
 * exec(), the deferred driver and the queue:run path restriction.
 */

declare(strict_types=1);

use SwiftFuse\Queue\QueueManager;
use SwiftFuse\Queue\Worker;
use SwiftFuse\Support\Config;
use SwiftFuse\Tests\Fixtures\QueueProbeJob;
use SwiftFuse\Tests\Support\PhpProcess;
use SwiftFuse\Tests\Support\TestRun;

require __DIR__ . '/bootstrap.php';

$test = new TestRun('Queue: reservation, interrupted jobs and drivers');

/**
 * Create an empty queue sandbox and point the queue configuration at it.
 *
 * @return array{dir: string, jobs: string, log: string, marker: string}
 */
function queueSandbox(): array
{
    $dir = sys_get_temp_dir() . '/swiftfuse-queue-' . bin2hex(random_bytes(6));
    mkdir($dir . '/jobs', 0755, true);

    $sandbox = ['dir' => $dir, 'jobs' => $dir . '/jobs', 'log' => $dir . '/queue.log', 'marker' => $dir . '/marker'];
    Config::set('queue.path', $sandbox['jobs']);
    Config::set('queue.log', $sandbox['log']);

    register_shutdown_function(static function () use ($dir): void {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    });

    return $sandbox;
}

/**
 * Write a job file the way SwiftFusePHP 0.10.0 did: serialize() into "<date>_<uniqid>.job".
 *
 * @param string $jobsPath Queue directory.
 * @param QueueProbeJob $job The job to store.
 * @return string Path of the job file.
 */
function legacyJob(string $jobsPath, QueueProbeJob $job): string
{
    $file = $jobsPath . '/' . date('YmdHis') . '_' . uniqid('', true) . '.job';
    file_put_contents($file, serialize($job), LOCK_EX);

    return $file;
}

/**
 * Start the queue probe fixture in the background.
 *
 * @param array{jobs: string, log: string} $sandbox The queue sandbox.
 * @param array<int, string> $arguments Command and arguments of the probe.
 * @param array<string, string> $ini php.ini settings for the child.
 * @return array{process: resource, pipes: array<int, resource>}
 */
function startProbe(array $sandbox, array $arguments, array $ini = []): array
{
    return PhpProcess::start(
        __DIR__ . '/Fixtures/queue-probe.php',
        [$sandbox['jobs'], $sandbox['log'], ...$arguments],
        $ini
    );
}

/**
 * Run the queue probe fixture and wait for it.
 *
 * @param array{jobs: string, log: string} $sandbox The queue sandbox.
 * @param array<int, string> $arguments Command and arguments of the probe.
 * @param array<string, string> $ini php.ini settings for the child.
 * @return array{exitCode: int, stdout: string, stderr: string}
 */
function runProbe(array $sandbox, array $arguments, array $ini = []): array
{
    return PhpProcess::wait(startProbe($sandbox, $arguments, $ini));
}

/**
 * Wait until a file matching the pattern exists.
 *
 * @param string $pattern glob() pattern.
 * @param float $timeout Seconds to wait at most.
 * @return string|null The first match, or null on timeout.
 */
function waitFor(string $pattern, float $timeout = 10.0): ?string
{
    $deadline = microtime(true) + $timeout;
    do {
        $matches = glob($pattern) ?: [];
        if ($matches !== []) {
            return $matches[0];
        }
        usleep(20_000);
    } while (microtime(true) < $deadline);

    return null;
}

/**
 * Read the lines of a marker file.
 *
 * @param string $marker Marker file path.
 * @return array<int, string>
 */
function markerLines(string $marker): array
{
    return is_file($marker) ? file($marker, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
}

/**
 * List the file names in a directory, sorted.
 *
 * @param string $directory Directory to list.
 * @return array<int, string>
 */
function fileNames(string $directory): array
{
    $names = array_map('basename', array_filter(glob($directory . '/*') ?: [], 'is_file'));
    sort($names);

    return $names;
}

$test->test('two workers started together run a slow job exactly once', function () use ($test): void {
    $sandbox = queueSandbox();
    legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], 'slow', 1.0));

    $first = startProbe($sandbox, ['work']);
    $second = startProbe($sandbox, ['work']);
    $counts = [PhpProcess::wait($first)['stdout'], PhpProcess::wait($second)['stdout']];
    sort($counts);

    $test->assertSame(['slow'], markerLines($sandbox['marker']), 'handle() ran once');
    $test->assertSame(['0', '1'], $counts, 'one worker processed the job, the other skipped it');
    $test->assertSame([], fileNames($sandbox['jobs']), 'the job file was removed');
    $test->assertSame([], fileNames($sandbox['jobs'] . '/failed'), 'nothing was failed');
});

$test->test('four concurrent workers share six jobs without running any twice', function () use ($test): void {
    $sandbox = queueSandbox();
    foreach (range(1, 6) as $number) {
        legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], "job-{$number}", 0.2));
    }

    $workers = array_map(static fn (): array => startProbe($sandbox, ['work']), range(1, 4));
    $total = array_sum(array_map(static fn (array $worker): int => (int) PhpProcess::wait($worker)['stdout'], $workers));

    $lines = markerLines($sandbox['marker']);
    sort($lines);
    $test->assertSame(['job-1', 'job-2', 'job-3', 'job-4', 'job-5', 'job-6'], $lines, 'every job ran exactly once');
    $test->assertSame(6, $total, 'the workers report six processed jobs in total');
    $test->assertSame([], fileNames($sandbox['jobs']), 'no job file is left');
});

$test->test('a worker skips a job another process is running and leaves it alone', function () use ($test): void {
    $sandbox = queueSandbox();
    $file = legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], 'slow', 1.5));

    $owner = startProbe($sandbox, ['work']);
    $running = waitFor($sandbox['jobs'] . '/*.running');
    $test->assertSame($file . '.running', $running, 'the owner renamed the job to .running');

    $worker = new Worker(new QueueManager());
    $test->assertSame(0, $worker->work(), 'work() processed nothing');
    $test->assertTrue(is_file((string) $running), 'the .running file is untouched');
    $test->assertSame([], fileNames($sandbox['jobs'] . '/failed'), 'the running job was not failed');
    $test->assertSame('0', runProbe($sandbox, ['work'])['stdout'], 'another process also skips it');

    $test->assertSame('1', PhpProcess::wait($owner)['stdout'], 'the owner processed the job');
    $test->assertSame(['slow'], markerLines($sandbox['marker']), 'handle() ran once');
    $test->assertSame([], fileNames($sandbox['jobs']), 'the owner removed the job');
});

$test->test('runFile() returns false for a job reserved by another process', function () use ($test): void {
    $sandbox = queueSandbox();
    $worker = new Worker(new QueueManager());

    // Another process holds the lock but has not renamed the file yet.
    $locked = legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], 'locked'));
    $lock = PhpProcess::start(__DIR__ . '/Fixtures/queue-lock.php', [$locked, '1.0']);
    waitFor($locked . '.locked');
    $test->assertSame(false, $worker->runFile($locked), 'runFile() on a locked .job returns false');
    $test->assertTrue(is_file($locked), 'the locked job stays pending');
    PhpProcess::wait($lock);

    // Another process is running the job.
    $file = legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], 'slow', 1.0));
    $owner = startProbe($sandbox, ['run', $file]);
    $running = (string) waitFor($sandbox['jobs'] . '/*.running');
    $test->assertSame(false, $worker->runFile($file), 'runFile() on the reserved .job path returns false');
    $test->assertSame(false, $worker->runFile($running), 'runFile() on the .running path returns false');
    $test->assertSame([], fileNames($sandbox['jobs'] . '/failed'), 'the reserved job was not moved to failed/');
    $test->assertSame('1', PhpProcess::wait($owner)['stdout'], 'the owner ran the job');

    $test->assertSame(['slow'], markerLines($sandbox['marker']), 'only the owner executed a job');
    $test->assertTrue($worker->runFile($locked), 'the formerly locked job runs once released');
});

$test->test('an interrupted job goes to failed/ and is not run again', function () use ($test): void {
    $sandbox = queueSandbox();
    $file = legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], 'dies', 0.0, 'exit'));

    $result = runProbe($sandbox, ['work']);
    $test->assertSame(9, $result['exitCode'], 'the worker process died inside handle()');
    $test->assertTrue(is_file($file . '.running'), 'the dead worker left a .running file');

    $worker = new Worker(new QueueManager());
    $test->assertSame(0, $worker->work(), 'the interrupted job is not counted as processed');
    $test->assertSame(0, $worker->work(), 'a second pass has nothing to do');

    $test->assertSame(['dies'], markerLines($sandbox['marker']), 'handle() did not run again');
    $test->assertSame([], fileNames($sandbox['jobs']), 'the .running file is gone');
    $test->assertSame([basename($file)], fileNames($sandbox['jobs'] . '/failed'), 'the job is in failed/ under its .job name');
    $test->assertTrue(
        str_contains((string) file_get_contents($sandbox['log']), 'FAILED ' . basename($file) . ': Worker interrupted'),
        'queue.log records the interruption'
    );
});

$test->test('a failing job goes to failed/ with its reason', function () use ($test): void {
    $sandbox = queueSandbox();
    $file = legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], 'broken', 0.0, 'throw'));
    file_put_contents($sandbox['jobs'] . '/garbage.job', 'not a serialized job');

    $test->assertSame(false, (new Worker(new QueueManager()))->runFile($file), 'runFile() reports the failure');
    $test->assertSame([basename($file)], fileNames($sandbox['jobs'] . '/failed'), 'the failed job was moved');
    $test->assertSame(1, (new Worker(new QueueManager()))->work(), 'the invalid payload is processed as a failure');

    $log = (string) file_get_contents($sandbox['log']);
    $test->assertTrue(str_contains($log, 'FAILED ' . basename($file) . ': Probe job broken failed.'), 'the exception message is logged');
    $test->assertTrue(str_contains($log, 'FAILED garbage.job: Payload is not a valid job.'), 'the invalid payload is logged');
    $test->assertSame([basename($file), 'garbage.job'], fileNames($sandbox['jobs'] . '/failed'), 'both are in failed/');
});

$test->test('async without exec() does not throw and leaves the job processable', function () use ($test): void {
    $sandbox = queueSandbox();
    $queue = new QueueManager('async', static fn (): bool => false);

    $first = $queue->dispatch(new QueueProbeJob($sandbox['marker'], 'first'));
    $second = $queue->dispatch(new QueueProbeJob($sandbox['marker'], 'second'));

    $test->assertTrue(is_file($first) && is_file($second), 'both jobs are queued');
    $warnings = substr_count((string) file_get_contents($sandbox['log']), 'WARNING async queue driver unavailable');
    $test->assertSame(1, $warnings, 'the fallback is logged once per process');
    $test->assertSame([], markerLines($sandbox['marker']), 'nothing ran during dispatch on the CLI');

    $test->assertSame(2, (new Worker(new QueueManager()))->work(), 'the worker processes both jobs');
    $test->assertSame(['first', 'second'], markerLines($sandbox['marker']), 'the jobs ran in dispatch order');
});

$test->test('async with exec in disable_functions dispatches without an error', function () use ($test): void {
    $sandbox = queueSandbox();

    $result = runProbe($sandbox, ['dispatch', 'async', $sandbox['marker']], ['disable_functions' => 'exec']);
    $test->assertSame(0, $result['exitCode'], 'dispatch() did not throw: ' . $result['stderr']);
    $test->assertTrue(is_file($result['stdout']), 'the job file exists');
    $test->assertTrue(
        str_contains((string) file_get_contents($sandbox['log']), 'exec() is disabled'),
        'the fallback warning names the reason'
    );

    $test->assertSame(1, (new Worker(new QueueManager()))->work(), 'the worker processes the job');
    $test->assertSame(['dispatched'], markerLines($sandbox['marker']), 'the job ran once');
});

$test->test('the deferred driver behaves like file on the CLI', function () use ($test): void {
    $sandbox = queueSandbox();

    $result = runProbe($sandbox, ['dispatch', 'deferred', $sandbox['marker']]);
    $test->assertSame(0, $result['exitCode'], 'the dispatching process ended cleanly');
    $test->assertTrue(is_file($result['stdout']), 'the job stays queued');
    $test->assertSame([], markerLines($sandbox['marker']), 'the job did not run at shutdown');

    $test->assertSame(1, (new Worker(new QueueManager()))->work(), 'the worker processes it');
    $test->assertSame(['dispatched'], markerLines($sandbox['marker']), 'the job ran once');
});

$test->test('queue:run rejects paths outside the queue directory', function () use ($test): void {
    $sandbox = queueSandbox();
    $outside = $sandbox['dir'] . '/outside.job';
    file_put_contents($outside, serialize(new QueueProbeJob($sandbox['marker'], 'outside')));
    mkdir($sandbox['jobs'] . '/failed');
    $failed = legacyJob($sandbox['jobs'] . '/failed', new QueueProbeJob($sandbox['marker'], 'failed'));

    foreach ([$outside, $sandbox['jobs'] . '/../outside.job', $failed] as $path) {
        $result = runProbe($sandbox, ['fuse', 'queue:run', $path]);
        $test->assertSame(1, $result['exitCode'], "exit code 1 for {$path}");
        $test->assertTrue(str_contains($result['stdout'], 'job files must be inside'), "clear message for {$path}");
    }

    $missing = runProbe($sandbox, ['fuse', 'queue:run', $sandbox['jobs'] . '/missing.job']);
    $test->assertSame(1, $missing['exitCode'], 'exit code 1 for a missing file');
    $test->assertTrue(str_contains($missing['stdout'], 'Job file not found'), 'a missing file is reported');

    $test->assertTrue(is_file($outside) && is_file($failed), 'the rejected files were not touched');
    $test->assertSame([], markerLines($sandbox['marker']), 'no rejected job ran');

    $inside = legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], 'inside'));
    $test->assertSame(0, runProbe($sandbox, ['fuse', 'queue:run', $inside])['exitCode'], 'a queued job runs');
    $test->assertSame(['inside'], markerLines($sandbox['marker']), 'only the queued job ran');
});

$test->test('a job written by 0.10.0 is processed by work() and runFile()', function () use ($test): void {
    $sandbox = queueSandbox();
    legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], 'by-work'));

    $worker = new Worker(new QueueManager());
    $test->assertSame(1, $worker->work(), 'work() processed the legacy job');

    $file = legacyJob($sandbox['jobs'], new QueueProbeJob($sandbox['marker'], 'by-run-file'));
    $test->assertTrue($worker->runFile($file), 'runFile() processed the legacy job');
    $test->assertSame(['by-work', 'by-run-file'], markerLines($sandbox['marker']), 'both ran once');
    $test->assertSame([], fileNames($sandbox['jobs']), 'both were removed');
});

$test->test('deferred jobs run in order after the response, even after exit', function () use ($test): void {
    $sandbox = queueSandbox();
    $port = random_int(20000, 45000);

    $command = [PHP_BINARY, '-d', 'xdebug.mode=off', '-S', "127.0.0.1:{$port}", __DIR__ . '/Fixtures/queue-http.php'];
    $environment = getenv() + ['SWIFTFUSE_QUEUE_PROBE' => $sandbox['dir']];
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $server = proc_open($command, [1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, null, $environment);
    $test->assertTrue(is_resource($server), 'the built-in server started');

    try {
        $request = static function (string $query) use ($port): ?string {
            $deadline = microtime(true) + 5;
            do {
                $body = @file_get_contents("http://127.0.0.1:{$port}/?{$query}");
                if ($body !== false) {
                    return $body;
                }
                usleep(50_000);
            } while (microtime(true) < $deadline);

            return null;
        };

        $test->assertSame('response', $request('finish=1'), 'the response carries no job output or error');
        $test->assertSame(['finish', 'first', 'second', 'third'], markerLines($sandbox['marker']), 'one finish, then the jobs in order');
        $test->assertSame(1, count(fileNames($sandbox['jobs'] . '/failed')), 'the failing job went to failed/');
        $test->assertTrue(
            str_contains((string) file_get_contents($sandbox['log']), 'Probe job second failed.'),
            'the failure is logged'
        );
        $test->assertSame([], fileNames($sandbox['jobs']), 'no job is left pending');

        unlink($sandbox['marker']);
        $test->assertSame('response', $request('finish=0'), 'without a finish function the request still responds');
        $test->assertSame([], markerLines($sandbox['marker']), 'no job ran inside the request');
        $test->assertSame(3, count(fileNames($sandbox['jobs'])), 'the jobs wait for the worker');
    } finally {
        proc_terminate($server);
        proc_close($server);
    }
});

$test->finish();
