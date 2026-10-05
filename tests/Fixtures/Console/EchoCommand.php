<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures\Console;

use SwiftFuse\Contracts\CommandInterface;

/**
 * Prints its arguments as JSON and exits with the code given by "--exit=<code>".
 */
final class EchoCommand implements CommandInterface
{
    /**
     * @return string
     */
    public function name(): string
    {
        return 'probe:echo';
    }

    /**
     * @return string
     */
    public function description(): string
    {
        return 'Print the arguments';
    }

    /**
     * @param array<int, string> $arguments CLI arguments that follow the command name.
     * @return int The code given by "--exit=<code>", or 0.
     */
    public function handle(array $arguments): int
    {
        fwrite(STDOUT, 'arguments=' . json_encode($arguments) . PHP_EOL);

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--exit=')) {
                return (int) substr($argument, 7);
            }
        }

        return 0;
    }
}
