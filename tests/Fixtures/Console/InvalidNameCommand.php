<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures\Console;

/**
 * Declares the name "Monthly Report".
 */
final class InvalidNameCommand extends NamedCommand
{
    /**
     * @return string
     */
    public function name(): string
    {
        return 'Monthly Report';
    }
}
