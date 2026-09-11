<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Support;

use Throwable;

/**
 * Dependency-free harness for one test script.
 *
 * Runs named test cases, prints one "ok" or "not ok" line per case, and ends the
 * process with exit code 0 when every case passed, 1 when any case failed, or
 * SKIP_EXIT_CODE when the script cannot run in the current environment.
 */
final class TestRun
{
    /**
     * Exit code of a skipped script (the Automake convention), distinct from a failure.
     *
     * @var int
     */
    public const SKIP_EXIT_CODE = 77;

    /**
     * Number of cases that passed.
     *
     * @var int
     */
    private int $passed = 0;

    /**
     * Number of cases that failed.
     *
     * @var int
     */
    private int $failed = 0;

    /**
     * Failure messages of the case that is running.
     *
     * @var array<int, string>
     */
    private array $caseFailures = [];

    /**
     * @param string $title Title printed before the results of the script.
     */
    public function __construct(string $title)
    {
        $this->write("# {$title}");
    }

    /**
     * Run a test case; an unexpected throwable fails the case without stopping the script.
     *
     * @param string $name What the case verifies.
     * @param callable(): void $body The case body, asserting through this run.
     * @return void
     */
    public function test(string $name, callable $body): void
    {
        $this->caseFailures = [];

        try {
            $body();
        } catch (Throwable $exception) {
            $this->caseFailures[] = sprintf(
                'unexpected %s: %s at %s:%d',
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
        }

        if ($this->caseFailures === []) {
            $this->passed++;
            $this->write("ok - {$name}");
            return;
        }

        $this->failed++;
        $this->write("not ok - {$name}");
        foreach ($this->caseFailures as $failure) {
            $this->write("    {$failure}");
        }
    }

    /**
     * Assert that a condition holds.
     *
     * @param bool $condition The condition that must be true.
     * @param string $description What the assertion verifies.
     * @return void
     */
    public function assertTrue(bool $condition, string $description): void
    {
        if (!$condition) {
            $this->caseFailures[] = "failed: {$description}";
        }
    }

    /**
     * Assert that two values are identical (===).
     *
     * @param mixed $expected The expected value.
     * @param mixed $actual The actual value.
     * @param string $description What the assertion verifies.
     * @return void
     */
    public function assertSame(mixed $expected, mixed $actual, string $description): void
    {
        if ($expected !== $actual) {
            $this->caseFailures[] = sprintf(
                'failed: %s; expected %s, got %s',
                $description,
                $this->export($expected),
                $this->export($actual)
            );
        }
    }

    /**
     * Assert that a callback throws an instance of the given class.
     *
     * @template T of Throwable
     *
     * @param class-string<T> $class Expected throwable class or parent class.
     * @param callable(): mixed $callback Code expected to throw.
     * @param string $description What the assertion verifies.
     * @return T|null The caught throwable, or null when the assertion failed.
     */
    public function assertThrows(string $class, callable $callback, string $description): ?Throwable
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            if ($exception instanceof $class) {
                return $exception;
            }

            $this->caseFailures[] = sprintf(
                'failed: %s; expected %s, got %s: %s',
                $description,
                $class,
                $exception::class,
                $exception->getMessage()
            );
            return null;
        }

        $this->caseFailures[] = "failed: {$description}; expected {$class}, nothing was thrown";
        return null;
    }

    /**
     * End the script without running the remaining cases.
     *
     * The script reports a skip, unless a case already failed.
     *
     * @param string $reason Why the remaining cases cannot run.
     * @return never
     */
    public function skipRemaining(string $reason): never
    {
        $this->write("# SKIP remaining cases: {$reason}");
        $this->finish(self::SKIP_EXIT_CODE);
    }

    /**
     * Print the summary and end the process with the matching exit code.
     *
     * @param int $successCode Exit code used when no case failed.
     * @return never
     */
    public function finish(int $successCode = 0): never
    {
        $this->write(sprintf('# %d passed, %d failed', $this->passed, $this->failed));
        exit($this->failed > 0 ? 1 : $successCode);
    }

    /**
     * Describe a value for a failure message.
     *
     * @param mixed $value The value to describe.
     * @return string
     */
    private function export(mixed $value): string
    {
        $description = is_object($value)
            ? get_debug_type($value) . '#' . spl_object_id($value)
            : var_export($value, true);

        return strlen($description) > 400 ? substr($description, 0, 400) . '...' : $description;
    }

    /**
     * Write a line to standard output.
     *
     * @param string $line The line to print.
     * @return void
     */
    private function write(string $line): void
    {
        fwrite(STDOUT, $line . PHP_EOL);
    }
}
