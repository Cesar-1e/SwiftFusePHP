<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures\Console;

/**
 * Declares the name "queue:work".
 */
final class BuiltInNameCommand extends NamedCommand
{
    /**
     * @return string
     */
    public function name(): string
    {
        return 'queue:work';
    }
}
