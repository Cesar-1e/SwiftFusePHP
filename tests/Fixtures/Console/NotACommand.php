<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures\Console;

/**
 * Looks like a command but does not implement CommandInterface.
 */
final class NotACommand
{
    /**
     * @return string
     */
    public function name(): string
    {
        return 'probe:not-a-command';
    }
}
