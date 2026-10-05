<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures\Console;

use RuntimeException;
use SwiftFuse\Contracts\CommandInterface;

/**
 * Throws from handle().
 */
final class FailingCommand implements CommandInterface
{
    /**
     * @return string
     */
    public function name(): string
    {
        return 'probe:fail';
    }

    /**
     * @return string
     */
    public function description(): string
    {
        return 'Always fails';
    }

    /**
     * @param array<int, string> $arguments CLI arguments that follow the command name.
     * @return int
     *
     * @throws RuntimeException Always.
     */
    public function handle(array $arguments): int
    {
        throw new RuntimeException('report source is missing');
    }
}
