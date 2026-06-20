<?php

declare(strict_types=1);

namespace SwiftFuse\Console;

use SwiftFuse\Queue\QueueManager;
use SwiftFuse\Queue\Worker;

/**
 * Console kernel for the "fuse" command-line tool.
 *
 * Dispatches CLI commands that boost developer productivity: generating the app
 * key, running the queue worker, and scaffolding controllers/jobs. The command
 * set is intentionally small and easy to extend.
 */
final class Kernel
{
    /**
     * Run the console application for the given CLI arguments.
     *
     * @param array<int, string> $argv Raw CLI arguments (including the script name).
     * @return int Process exit code (0 = success).
     */
    public function handle(array $argv): int
    {
        $command = $argv[1] ?? 'list';
        $arguments = array_slice($argv, 2);

        return match ($command) {
            'key:generate'     => $this->keyGenerate(),
            'queue:work'       => $this->queueWork($arguments),
            'queue:run'        => $this->queueRun($arguments),
            'make:controller'  => $this->makeController($arguments),
            'make:job'         => $this->makeJob($arguments),
            'list', '--help', '-h' => $this->listCommands(),
            default            => $this->unknown($command),
        };
    }

    /**
     * Generate a random APP_KEY and persist it to the .env file.
     *
     * @return int Exit code.
     */
    private function keyGenerate(): int
    {
        $key = 'base64:' . base64_encode(random_bytes(32));
        $envPath = base_path('.env');

        if (!is_file($envPath)) {
            $this->line("No .env file found. Copy .env.example to .env first.");
            return 1;
        }

        $contents = (string) file_get_contents($envPath);
        if (preg_match('/^APP_KEY=.*$/m', $contents) === 1) {
            $contents = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $contents);
        } else {
            $contents .= PHP_EOL . 'APP_KEY=' . $key . PHP_EOL;
        }

        file_put_contents($envPath, $contents);
        $this->line("Application key set successfully.");

        return 0;
    }

    /**
     * Run the queue worker once, or as a long-running daemon with --daemon.
     *
     * @param array<int, string> $arguments CLI arguments.
     * @return int Exit code.
     */
    private function queueWork(array $arguments): int
    {
        $worker = new Worker(new QueueManager());

        if (in_array('--daemon', $arguments, true)) {
            $this->line("Queue worker started (daemon mode). Press Ctrl+C to stop.");
            $worker->daemon();
            return 0;
        }

        $count = $worker->work();
        $this->line("Processed {$count} job(s).");

        return 0;
    }

    /**
     * Process a single serialized job file (used by the async queue driver).
     *
     * @param array<int, string> $arguments CLI arguments; the first is the job file.
     * @return int Exit code.
     */
    private function queueRun(array $arguments): int
    {
        $file = $arguments[0] ?? '';
        if ($file === '') {
            $this->line("Usage: fuse queue:run <job-file>");
            return 1;
        }

        return (new Worker(new QueueManager()))->runFile($file) ? 0 : 1;
    }

    /**
     * Scaffold a new application controller in app/Controllers.
     *
     * @param array<int, string> $arguments CLI arguments; the first is the class name.
     * @return int Exit code.
     */
    private function makeController(array $arguments): int
    {
        $name = $this->studly($arguments[0] ?? '');
        if ($name === '') {
            $this->line("Usage: fuse make:controller <Name>");
            return 1;
        }

        $folder = strtolower(preg_replace('/Controller$/', '', $name));
        $class = str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
        $path = app_path("Controllers/{$class}.php");

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Controllers;

        use SwiftFuse\\Http\\Controller;

        /**
         * {$class}.
         */
        final class {$class} extends Controller
        {
            /**
             * The view folder for this controller.
             *
             * @var string
             */
            protected string \$folder = '{$folder}';
        }

        PHP;

        return $this->writeStub($path, $stub, $class);
    }

    /**
     * Scaffold a new background job in app/Jobs.
     *
     * @param array<int, string> $arguments CLI arguments; the first is the class name.
     * @return int Exit code.
     */
    private function makeJob(array $arguments): int
    {
        $class = $this->studly($arguments[0] ?? '');
        if ($class === '') {
            $this->line("Usage: fuse make:job <Name>");
            return 1;
        }

        $path = app_path("Jobs/{$class}.php");

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Jobs;

        use SwiftFuse\\Contracts\\JobInterface;

        /**
         * {$class} background job.
         */
        final class {$class} implements JobInterface
        {
            /**
             * Execute the job.
             *
             * @return void
             */
            public function handle(): void
            {
                // TODO: implement the job logic.
            }
        }

        PHP;

        return $this->writeStub($path, $stub, $class);
    }

    /**
     * Print the list of available commands.
     *
     * @return int Exit code.
     */
    private function listCommands(): int
    {
        $this->line("SwiftFusePHP CLI (fuse)");
        $this->line("");
        $this->line("Available commands:");
        $this->line("  key:generate              Generate and set APP_KEY in .env");
        $this->line("  queue:work [--daemon]     Process pending background jobs");
        $this->line("  queue:run <file>          Process a single job file");
        $this->line("  make:controller <Name>    Create a new App\\Controllers class");
        $this->line("  make:job <Name>           Create a new App\\Jobs class");

        return 0;
    }

    /**
     * Report an unknown command.
     *
     * @param string $command The command that was not recognized.
     * @return int Exit code.
     */
    private function unknown(string $command): int
    {
        $this->line("Unknown command: {$command}");
        $this->listCommands();

        return 1;
    }

    /**
     * Write a stub to disk, refusing to overwrite an existing file.
     *
     * @param string $path Destination file path.
     * @param string $stub File contents.
     * @param string $class Class name (for messaging).
     * @return int Exit code.
     */
    private function writeStub(string $path, string $stub, string $class): int
    {
        if (is_file($path)) {
            $this->line("{$class} already exists at {$path}.");
            return 1;
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $stub);
        $this->line("Created {$class} at {$path}.");

        return 0;
    }

    /**
     * Convert a name to StudlyCase.
     *
     * @param string $value Raw name.
     * @return string
     */
    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', trim($value))));
    }

    /**
     * Write a line to standard output.
     *
     * @param string $message The message to print.
     * @return void
     */
    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
