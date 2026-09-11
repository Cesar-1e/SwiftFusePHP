<?php

/**
 * Built-in autoloader: without vendor/, the PSR-4 roots of composer.json are
 * registered as long as their directories are inside the project.
 *
 * Each case builds a throwaway project in the system temp directory with a copy
 * of bootstrap/autoload.php, and probes it in a fresh PHP process.
 */

declare(strict_types=1);

use SwiftFuse\Tests\Support\PhpProcess;
use SwiftFuse\Tests\Support\TestRun;

require __DIR__ . '/bootstrap.php';

$test = new TestRun('Autoloader: PSR-4 roots from composer.json without Composer');

$workspace = sys_get_temp_dir() . '/swiftfuse-autoload-' . bin2hex(random_bytes(6));

register_shutdown_function(static function () use ($workspace): void {
    if (!is_dir($workspace)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($workspace, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($workspace);
});

/**
 * Write files below a directory, creating folders as needed.
 *
 * @param string $directory Absolute base directory.
 * @param array<string, string> $files Relative path => contents.
 * @return void
 */
$writeFiles = static function (string $directory, array $files): void {
    foreach ($files as $relative => $contents) {
        $path = "{$directory}/{$relative}";
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
            throw new RuntimeException('Cannot create ' . dirname($path));
        }
        file_put_contents($path, $contents);
    }
};

/**
 * Source of a class file whose SOURCE constant tells where it was loaded from.
 *
 * @param string $class Fully qualified class name.
 * @param string $source Value of the SOURCE constant.
 * @return string
 */
$classSource = static function (string $class, string $source = 'default'): string {
    $separator = (int) strrpos($class, '\\');
    $namespace = substr($class, 0, $separator);
    $name = substr($class, $separator + 1);

    return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n"
        . "final class {$name}\n{\n    public const SOURCE = '{$source}';\n}\n";
};

/**
 * Create a project with the framework autoloader and the given composer.json and files.
 *
 * @param string $name Project folder inside the workspace.
 * @param array<string, mixed>|string $composer composer.json contents, encoded when given as an array.
 * @param array<string, string> $files Relative path => contents.
 * @return string Absolute project root.
 */
$makeProject = static function (string $name, array|string $composer, array $files = []) use (
    $workspace,
    $writeFiles
): string {
    $root = "{$workspace}/{$name}";
    $files['bootstrap/autoload.php'] = (string) file_get_contents(BASE_PATH . '/bootstrap/autoload.php');
    $files['composer.json'] = is_array($composer)
        ? (string) json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        : $composer;
    $writeFiles($root, $files);

    return $root;
};

/**
 * Load a project's autoloader in a fresh PHP process and probe classes.
 *
 * @param string $root Project root.
 * @param array<int, string> $classes Classes to probe.
 * @return array{loaded: array<string, bool>, sources: array<string, string|null>, warnings: array<int, string>}
 */
$probe = static function (string $root, array $classes): array {
    $result = PhpProcess::run(__DIR__ . '/Fixtures/autoload-probe.php', [$root, ...$classes]);
    $report = json_decode($result['stdout'], true);
    if (!is_array($report)) {
        throw new RuntimeException("The autoload probe failed: {$result['stdout']}{$result['stderr']}");
    }

    return $report;
};

$standardRoots = ['SwiftFuse\\' => 'src/SwiftFuse/', 'App\\' => 'app/'];

$test->test('a root declared in composer.json loads without vendor/, next to the built-in roots', function () use (
    $test,
    $makeProject,
    $classSource,
    $probe,
    $standardRoots
): void {
    $composer = ['autoload' => ['psr-4' => $standardRoots + ['Extensions\\' => 'extensions/']]];
    $root = $makeProject('declared', $composer, [
        'extensions/Billing/InvoiceField.php' => $classSource('Extensions\\Billing\\InvoiceField'),
        'app/Services/ProbeService.php' => $classSource('App\\Services\\ProbeService'),
        'src/SwiftFuse/Probe/CoreProbe.php' => $classSource('SwiftFuse\\Probe\\CoreProbe'),
    ]);
    $classes = ['Extensions\\Billing\\InvoiceField', 'App\\Services\\ProbeService', 'SwiftFuse\\Probe\\CoreProbe'];

    $report = $probe($root, $classes);

    $test->assertSame(
        array_fill_keys($classes, true),
        $report['loaded'],
        'the declared root and both built-in roots load'
    );
    $test->assertSame([], $report['warnings'], 'no warning is raised');
});

$test->test('with vendor/autoload.php present, composer.json roots are left to Composer', function () use (
    $test,
    $makeProject,
    $classSource,
    $probe,
    $standardRoots
): void {
    $composer = ['autoload' => ['psr-4' => $standardRoots + ['Extensions\\' => 'extensions/']]];
    $root = $makeProject('with-vendor', $composer, [
        'extensions/Billing/InvoiceField.php' => $classSource('Extensions\\Billing\\InvoiceField'),
        'app/Services/ProbeService.php' => $classSource('App\\Services\\ProbeService'),
        'vendor/autoload.php' => "<?php\n\ndeclare(strict_types=1);\n",
    ]);

    $report = $probe($root, ['Extensions\\Billing\\InvoiceField', 'App\\Services\\ProbeService']);

    $test->assertSame(
        ['Extensions\\Billing\\InvoiceField' => false, 'App\\Services\\ProbeService' => true],
        $report['loaded'],
        'only the built-in roots load; the composer.json root is left to Composer'
    );
});

$test->test('roots that resolve outside the project are ignored with a warning', function () use (
    $test,
    $makeProject,
    $classSource,
    $probe,
    $writeFiles,
    $workspace
): void {
    $writeFiles("{$workspace}/shared", [
        'Outside/Leak.php' => $classSource('Outside\\Leak'),
        'Absolute/Leak.php' => $classSource('Absolute\\Leak'),
    ]);
    $root = $makeProject('outside', ['autoload' => ['psr-4' => [
        'Outside\\' => '../shared/Outside/',
        'Absolute\\' => "{$workspace}/shared/Absolute/",
        'Wrapped\\' => 'phar://archive.phar/src/',
        'Extensions\\' => 'extensions/',
    ]]], [
        'extensions/Billing/InvoiceField.php' => $classSource('Extensions\\Billing\\InvoiceField'),
    ]);
    $expected = [
        'Outside\\Leak' => false,
        'Absolute\\Leak' => false,
        'Wrapped\\Leak' => false,
        'Extensions\\Billing\\InvoiceField' => true,
    ];

    $report = $probe($root, array_keys($expected));

    $test->assertSame($expected, $report['loaded'], 'only the root inside the project loads, though the files exist');
    $test->assertSame(3, count($report['warnings']), 'one warning per ignored root');
    foreach (['Outside\\', 'Absolute\\', 'Wrapped\\'] as $index => $prefix) {
        $test->assertTrue(
            str_contains($report['warnings'][$index] ?? '', "\"{$prefix}\""),
            "the warning names the ignored prefix {$prefix}"
        );
    }
});

$test->test('a prefix may list several directories, and nested prefixes are searched first', function () use (
    $test,
    $makeProject,
    $classSource,
    $probe
): void {
    $root = $makeProject('nested', ['autoload' => ['psr-4' => [
        'Shared\\' => ['lib/', 'extra/'],
        'Extensions\\' => './extensions',
        'Extensions\\Billing\\' => 'billing/',
    ]]], [
        'lib/Alpha.php' => $classSource('Shared\\Alpha', 'lib'),
        'extra/Beta.php' => $classSource('Shared\\Beta', 'extra'),
        'billing/Rule.php' => $classSource('Extensions\\Billing\\Rule', 'billing'),
        'extensions/Billing/Rule.php' => $classSource('Extensions\\Billing\\Rule', 'extensions'),
        'extensions/Billing/InvoiceField.php' => $classSource('Extensions\\Billing\\InvoiceField', 'extensions'),
    ]);
    $expectedSources = [
        'Shared\\Alpha' => 'lib',
        'Shared\\Beta' => 'extra',
        'Extensions\\Billing\\Rule' => 'billing',
        'Extensions\\Billing\\InvoiceField' => 'extensions',
    ];

    $report = $probe($root, array_keys($expectedSources));

    $test->assertSame($expectedSources, $report['sources'], 'each class loaded from the expected directory');
    $test->assertSame([], $report['warnings'], 'no warning is raised');
});

$test->test('an invalid composer.json is ignored with a warning and the built-in roots keep working', function () use (
    $test,
    $makeProject,
    $classSource,
    $probe
): void {
    $root = $makeProject('invalid-json', '{ "autoload": ', [
        'app/Services/ProbeService.php' => $classSource('App\\Services\\ProbeService'),
    ]);

    $report = $probe($root, ['App\\Services\\ProbeService']);

    $test->assertSame(['App\\Services\\ProbeService' => true], $report['loaded'], 'the built-in roots still load');
    $test->assertSame(1, count($report['warnings']), 'one warning reports the unreadable manifest');
});

$test->test('a prefix without the trailing namespace separator is ignored with a warning', function () use (
    $test,
    $makeProject,
    $classSource,
    $probe
): void {
    $root = $makeProject('no-separator', ['autoload' => ['psr-4' => ['NoSeparator' => 'lib/']]], [
        'lib/Thing.php' => $classSource('NoSeparator\\Thing'),
    ]);

    $report = $probe($root, ['NoSeparator\\Thing']);

    $test->assertSame(['NoSeparator\\Thing' => false], $report['loaded'], 'the invalid prefix is not registered');
    $test->assertTrue(str_contains($report['warnings'][0] ?? '', '"NoSeparator"'), 'the warning names the prefix');
});

$test->finish();
