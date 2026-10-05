<?php

/**
 * Application console commands.
 *
 * Read by SwiftFuse\Console\Kernel (the `php fuse` CLI). List here the classes
 * of your own commands; each one must implement SwiftFuse\Contracts\CommandInterface
 * and is created without constructor arguments. Its name() is what you type after
 * `php fuse`, and description() is shown by `php fuse list`.
 *
 * Built-in commands (key:generate, queue:work, ...) always take precedence and
 * cannot be replaced. Create a command with `php fuse make:command <Name>`.
 */

declare(strict_types=1);

return [
    'commands' => [
        // App\Console\GenerateMonthlyReport::class,   // php fuse reports:monthly
    ],
];
