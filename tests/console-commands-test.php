<?php

/**
 * Console: application commands registered in config/console.php, their
 * validation, "fuse list", "make:command", and the CLI without the configuration.
 *
 * Each case runs the console kernel in a fresh PHP process through the
 * console-kernel fixture, with the command registry given as JSON.
 */

declare(strict_types=1);

use SwiftFuse\Tests\Fixtures\Console\AlphaCommand;
use SwiftFuse\Tests\Fixtures\Console\BuiltInNameCommand;
use SwiftFuse\Tests\Fixtures\Console\DuplicateEchoCommand;
use SwiftFuse\Tests\Fixtures\Console\EchoCommand;
use SwiftFuse\Tests\Fixtures\Console\FailingCommand;
use SwiftFuse\Tests\Fixtures\Console\InvalidNameCommand;
use SwiftFuse\Tests\Fixtures\Console\NotACommand;
use SwiftFuse\Tests\Support\PhpProcess;
use SwiftFuse\Tests\Support\TestRun;

require __DIR__ . '/bootstrap.php';

$test = new TestRun('Console: application commands');

/**
 * Run the console kernel with a command registry.
 *
 * @param array<int, mixed>|null $registry Value of console.commands, or null to leave it absent.
 * @param array<int, string> $arguments Arguments after "fuse".
 * @return array{exitCode: int, stdout: string, stderr: string}
 */
function fuse(?array $registry, array $arguments): array
{
    return PhpProcess::run(
        __DIR__ . '/Fixtures/console-kernel.php',
        [$registry === null ? '-' : json_encode($registry, JSON_THROW_ON_ERROR), ...$arguments]
    );
}

/**
 * The built-in section of "fuse list".
 *
 * @var string
 */
const BUILT_IN_LIST = <<<'TXT'
SwiftFusePHP CLI (fuse)

Available commands:
  key:generate              Generate and set APP_KEY in .env
  queue:work [--daemon]     Process pending background jobs
  queue:run <file>          Process a single job file from the queue directory
  make:controller <Name>    Create a new App\Controllers class
  make:job <Name>           Create a new App\Jobs class
  make:command <Name>       Create a new App\Console command class
  assets:publish            Publish third-party assets to public/
      [--force] [--link]      --force overwrites, --link symlinks (copy fallback)

TXT;

$test->test('a registered command runs with its arguments and its exit code is propagated', function () use ($test): void {
    $result = fuse([EchoCommand::class], ['probe:echo', 'one', '--two']);
    $test->assertSame(0, $result['exitCode'], 'exit code 0');
    $test->assertSame('arguments=["one","--two"]' . PHP_EOL, $result['stdout'], 'arguments after the name');
    $test->assertSame('', $result['stderr'], 'nothing on STDERR');

    $result = fuse([AlphaCommand::class, EchoCommand::class], ['probe:echo', '--exit=3']);
    $test->assertSame(3, $result['exitCode'], 'exit code returned by handle()');
});

$test->test('a class that does not exist is reported and exits with 1', function () use ($test): void {
    $result = fuse([EchoCommand::class, 'App\\Console\\MissingCommand'], ['probe:echo']);
    $test->assertSame(1, $result['exitCode'], 'exit code 1');
    $test->assertSame('', $result['stdout'], 'the command did not run');
    $test->assertTrue(
        str_contains($result['stderr'], 'Console command class App\\Console\\MissingCommand does not exist.'),
        'message on STDERR'
    );
});

$test->test('a class that does not implement CommandInterface is reported and exits with 1', function () use ($test): void {
    $result = fuse([NotACommand::class], ['probe:not-a-command']);
    $test->assertSame(1, $result['exitCode'], 'exit code 1');
    $test->assertTrue(
        str_contains($result['stderr'], NotACommand::class . ' must implement SwiftFuse\\Contracts\\CommandInterface.'),
        'message on STDERR'
    );
});

$test->test('an invalid name is reported and exits with 1', function () use ($test): void {
    $result = fuse([InvalidNameCommand::class], ['list']);
    $test->assertSame(1, $result['exitCode'], 'exit code 1');
    $test->assertTrue(
        str_contains($result['stderr'], 'has an invalid name "Monthly Report"'),
        'message on STDERR'
    );
});

$test->test('a name that collides with a built-in command is reported and exits with 1', function () use ($test): void {
    $result = fuse([BuiltInNameCommand::class], ['probe:echo']);
    $test->assertSame(1, $result['exitCode'], 'exit code 1');
    $test->assertTrue(
        str_contains($result['stderr'], 'cannot use the name "queue:work": it is a built-in command.'),
        'message on STDERR'
    );
});

$test->test('a name registered by two classes is reported and exits with 1', function () use ($test): void {
    $result = fuse([EchoCommand::class, DuplicateEchoCommand::class], ['probe:echo']);
    $test->assertSame(1, $result['exitCode'], 'exit code 1');
    $test->assertSame('', $result['stdout'], 'the command did not run');
    $test->assertTrue(
        str_contains(
            $result['stderr'],
            'Console command name "probe:echo" is registered twice: ' . EchoCommand::class . ' and ' . DuplicateEchoCommand::class . '.'
        ),
        'message on STDERR'
    );
});

$test->test('an exception thrown by handle() is reported and exits with 1', function () use ($test): void {
    $result = fuse([FailingCommand::class], ['probe:fail']);
    $test->assertSame(1, $result['exitCode'], 'exit code 1');
    $test->assertSame('Command probe:fail failed: report source is missing' . PHP_EOL, $result['stderr'], 'message on STDERR');
});

$test->test('built-in commands take precedence and ignore the registry', function () use ($test): void {
    $result = fuse(['App\\Console\\MissingCommand'], ['make:job']);
    $test->assertSame(1, $result['exitCode'], 'exit code of make:job without a name');
    $test->assertSame('Usage: fuse make:job <Name>' . PHP_EOL, $result['stdout'], 'make:job ran');
    $test->assertSame('', $result['stderr'], 'the registry was not loaded');
});

$test->test('list shows the application commands in alphabetical order', function () use ($test): void {
    $result = fuse([EchoCommand::class, AlphaCommand::class], ['list']);
    $test->assertSame(0, $result['exitCode'], 'exit code 0');
    $test->assertSame(
        BUILT_IN_LIST
        . PHP_EOL
        . 'Application commands:' . PHP_EOL
        . '  alpha:task                First command in alphabetical order' . PHP_EOL
        . '  probe:echo                Print the arguments' . PHP_EOL,
        $result['stdout'],
        'list output'
    );
});

$test->test('without config/console.php the CLI behaves as before', function () use ($test): void {
    foreach ([null, []] as $registry) {
        $label = $registry === null ? 'absent' : 'empty';

        $result = fuse($registry, ['list']);
        $test->assertSame(0, $result['exitCode'], "list exit code ({$label})");
        $test->assertSame(BUILT_IN_LIST, $result['stdout'], "list shows only built-in commands ({$label})");
        $test->assertSame('', $result['stderr'], "nothing on STDERR ({$label})");

        $result = fuse($registry, ['reports:monthly']);
        $test->assertSame(1, $result['exitCode'], "unknown command exit code ({$label})");
        $test->assertSame('Unknown command: reports:monthly' . PHP_EOL . BUILT_IN_LIST, $result['stdout'], "unknown command output ({$label})");
    }

    $result = PhpProcess::run(BASE_PATH . '/fuse', ['list']);
    $test->assertSame(0, $result['exitCode'], 'php fuse list with the shipped config/console.php');
    $test->assertTrue(!str_contains($result['stdout'], 'Application commands:'), 'no application section');
});

$test->test('make:command scaffolds a command that can be registered and run', function () use ($test): void {
    $class = 'Probe' . ucfirst(implode('', array_map(static fn (): string => chr(random_int(97, 122)), range(1, 8)))) . 'Command';
    $directory = BASE_PATH . '/app/Console';
    $createdDirectory = !is_dir($directory);
    $path = "{$directory}/{$class}.php";

    try {
        $result = fuse(null, ['make:command', $class]);
        $test->assertSame(0, $result['exitCode'], 'exit code 0');
        $test->assertTrue(is_file($path), 'class file created in app/Console');
        $test->assertTrue(
            str_contains($result['stdout'], "Register it in config/console.php: 'commands' => [App\\Console\\{$class}::class]"),
            'registration reminder'
        );

        $name = 'app:' . strtolower(preg_replace('/(?<=[a-z])(?=[A-Z])/', '-', substr($class, 0, -7)));
        $result = fuse(["App\\Console\\{$class}"], ['list']);
        $test->assertTrue(str_contains($result['stdout'], "  {$name}"), 'generated command listed');

        $result = fuse(["App\\Console\\{$class}"], [$name]);
        $test->assertSame(0, $result['exitCode'], 'generated command runs');
        $test->assertSame('', $result['stderr'], 'nothing on STDERR');

        $result = fuse(null, ['make:command', $class]);
        $test->assertSame(1, $result['exitCode'], 'refuses to overwrite');

        $result = fuse(null, ['make:command', 'bad/name']);
        $test->assertSame(1, $result['exitCode'], 'invalid class name rejected');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
        if ($createdDirectory && is_dir($directory) && (scandir($directory) ?: []) === ['.', '..']) {
            rmdir($directory);
        }
    }
});

$test->finish();
