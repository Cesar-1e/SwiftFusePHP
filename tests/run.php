<?php

/**
 * Run the framework test scripts and report the overall result.
 *
 * Usage: php tests/run.php [name-filter]
 *
 * Every tests/*-test.php script runs in its own PHP process, so static state and
 * process exits never leak between scripts. The exit code is 0 when every script
 * passed or was skipped, and 1 when any script failed or none matched the filter.
 */

declare(strict_types=1);

use SwiftFuse\Tests\Support\PhpProcess;
use SwiftFuse\Tests\Support\TestRun;

require __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';
$scripts = glob(__DIR__ . '/*-test.php') ?: [];
sort($scripts);

if ($filter !== '') {
    $scripts = array_values(array_filter(
        $scripts,
        static fn (string $script): bool => str_contains(basename($script), $filter)
    ));
}

$totals = ['passed' => 0, 'failed' => 0, 'skipped' => 0];

foreach ($scripts as $script) {
    $result = PhpProcess::run($script);
    fwrite(STDOUT, $result['stdout']);
    if ($result['stderr'] !== '') {
        fwrite(STDERR, $result['stderr']);
    }

    $outcome = match ($result['exitCode']) {
        0 => 'passed',
        TestRun::SKIP_EXIT_CODE => 'skipped',
        default => 'failed',
    };
    $totals[$outcome]++;

    fwrite(STDOUT, sprintf('== %s: %s%s%s', basename($script), strtoupper($outcome), PHP_EOL, PHP_EOL));
}

fwrite(STDOUT, sprintf(
    '%d script(s): %d passed, %d failed, %d skipped.%s',
    count($scripts),
    $totals['passed'],
    $totals['failed'],
    $totals['skipped'],
    PHP_EOL
));

exit($totals['failed'] > 0 || $scripts === [] ? 1 : 0);
