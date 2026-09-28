<?php

/**
 * Subprocess fixture: hold an exclusive lock on a job file, like a worker that
 * has not renamed it yet.
 *
 * Usage: php queue-lock.php <job-file> <seconds>
 *
 * Creates "<job-file>.locked" once the lock is held, keeps it for the given
 * seconds, then releases it and removes the signal file.
 */

declare(strict_types=1);

[, $file, $seconds] = $argv + [null, '', '1'];

$handle = fopen($file, 'r+b');
if ($handle === false || !flock($handle, LOCK_EX)) {
    fwrite(STDERR, "Cannot lock {$file}" . PHP_EOL);
    exit(1);
}

touch($file . '.locked');
usleep((int) ((float) $seconds * 1_000_000));

flock($handle, LOCK_UN);
fclose($handle);
unlink($file . '.locked');
