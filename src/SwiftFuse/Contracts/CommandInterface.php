<?php

declare(strict_types=1);

namespace SwiftFuse\Contracts;

/**
 * Contract for application console commands.
 *
 * An application command is run by the "fuse" CLI ("php fuse <name> [arguments]")
 * once its class is listed in config/console.php under "commands". The console
 * kernel creates it without constructor arguments, so dependencies must be
 * resolved inside the command (e.g. with app() or config()).
 */
interface CommandInterface
{
    /**
     * The name used to call the command, e.g. "reports:monthly".
     *
     * Lowercase segments separated by ":" (letters, digits and "-", starting with
     * a letter). It must not collide with a built-in command.
     *
     * @return string
     */
    public function name(): string;

    /**
     * One-line description shown by "php fuse list".
     *
     * @return string
     */
    public function description(): string;

    /**
     * Execute the command.
     *
     * @param array<int, string> $arguments CLI arguments that follow the command name.
     * @return int Process exit code (0 = success).
     */
    public function handle(array $arguments): int;
}
