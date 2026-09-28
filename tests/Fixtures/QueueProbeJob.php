<?php

declare(strict_types=1);

namespace SwiftFuse\Tests\Fixtures;

use RuntimeException;
use SwiftFuse\Contracts\JobInterface;

/**
 * Job fixture that records each execution as one line in a marker file.
 *
 * After recording, it waits the given seconds and then behaves by mode: "ok"
 * returns, "throw" fails, "exit" ends the process mid-job (a dead worker), and
 * "echo" prints output that must never reach a response.
 */
final class QueueProbeJob implements JobInterface
{
    /**
     * @param string $marker File that receives one line per execution.
     * @param string $label Text written on the marker line.
     * @param float $seconds Seconds to wait after recording.
     * @param string $mode One of "ok", "throw", "exit" or "echo".
     */
    public function __construct(
        private string $marker,
        private string $label = 'run',
        private float $seconds = 0.0,
        private string $mode = 'ok'
    ) {
    }

    /**
     * Record the execution, wait, then act according to the mode.
     *
     * @return void
     *
     * @throws RuntimeException In "throw" mode.
     */
    public function handle(): void
    {
        file_put_contents($this->marker, $this->label . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($this->seconds > 0) {
            usleep((int) ($this->seconds * 1_000_000));
        }

        match ($this->mode) {
            'throw' => throw new RuntimeException("Probe job {$this->label} failed."),
            'exit'  => exit(9),
            'echo'  => print("output of {$this->label}"),
            default => null,
        };
    }
}
