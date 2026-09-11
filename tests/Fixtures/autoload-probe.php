<?php

/**
 * Subprocess fixture: require a project's bootstrap/autoload.php and report which classes load.
 *
 * Usage: php autoload-probe.php <project-root> <class> [<class> ...]
 *
 * Prints a JSON object with "loaded" (class => bool), "sources" (class => the
 * value of its SOURCE constant, or null) and "warnings" (messages raised while
 * the autoloader was being registered).
 */

declare(strict_types=1);

$warnings = [];
set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
    $warnings[] = $message;
    return true;
});

require $argv[1] . '/bootstrap/autoload.php';

restore_error_handler();

$loaded = [];
$sources = [];
foreach (array_slice($argv, 2) as $class) {
    $loaded[$class] = class_exists($class);
    $sources[$class] = $loaded[$class] && defined($class . '::SOURCE') ? constant($class . '::SOURCE') : null;
}

echo json_encode(['loaded' => $loaded, 'sources' => $sources, 'warnings' => $warnings], JSON_UNESCAPED_SLASHES);
