<?php

/**
 * Subprocess fixture: run the console kernel ("fuse") with a given command registry.
 *
 * Usage: php console-kernel.php <registry> [fuse arguments...]
 *
 *   <registry> is a JSON list of class names stored in console.commands, or "-"
 *   to leave the console configuration absent (a project without config/console.php).
 */

declare(strict_types=1);

use SwiftFuse\Console\Kernel;
use SwiftFuse\Support\Config;

require dirname(__DIR__) . '/bootstrap.php';

$registry = $argv[1] ?? '-';
if ($registry !== '-') {
    Config::set('console.commands', json_decode($registry, true, 512, JSON_THROW_ON_ERROR));
}

exit((new Kernel())->handle(['fuse', ...array_slice($argv, 2)]));
