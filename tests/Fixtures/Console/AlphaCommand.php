<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures\Console;

use SwiftFuse\Contracts\CommandInterface;

/**
 * Does nothing; sorts before EchoCommand in "fuse list".
 */
final class AlphaCommand implements CommandInterface
{
    /**
     * @return string
     */
    public function name(): string
    {
        return 'alpha:task';
    }

    /**
     * @return string
     */
    public function description(): string
    {
        return 'First command in alphabetical order';
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
