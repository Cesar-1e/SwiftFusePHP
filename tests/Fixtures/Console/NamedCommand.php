<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures\Console;

use SwiftFuse\Contracts\CommandInterface;

/**
 * Base for the fixtures whose only difference is the name they declare.
 */
abstract class NamedCommand implements CommandInterface
{
    /**
     * @return string
     */
    public function description(): string
    {
        return static::class;
    }

    /**
     * @param array<int, string> $arguments CLI arguments that follow the command name.
     * @return int
     */
    public function handle(array $arguments): int
    {
        return 0;
    }
}
