<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures\Console;

/**
 * Declares the name "probe:echo".
 */
final class DuplicateEchoCommand extends NamedCommand
{
    /**
     * @return string
     */
    public function name(): string
    {
        return 'probe:echo';
    }
}
