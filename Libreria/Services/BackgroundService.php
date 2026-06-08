<?php
/**
 * Minimal background task service for SwiftFusePHP.
 *
 * This service dispatches PHP scripts in a separate process.
 * It is designed for non-blocking background execution of lightweight jobs.
 */
class BackgroundService
{
    protected string $phpBinary;
    protected string $storagePath;

    public function __construct(string $phpBinary = 'php', string $storagePath = STORAGE_PATH)
    {
        $this->phpBinary = $phpBinary;
        $this->storagePath = rtrim($storagePath, '/') . '/';
    }

    /**
     * Dispatch a PHP script in the background.
     *
     * @param string $scriptRelativePath Relative path from application root to a PHP script.
     * @param array $arguments Optional payload data to pass to the script.
     * @return bool True when the process has been queued successfully.
     */
    public function dispatch(string $scriptRelativePath, array $arguments = []): bool
    {
        $script = RUTA_APP . ltrim($scriptRelativePath, '/');
        if (!file_exists($script)) {
            return false;
        }

        $payloadFile = $this->storagePath . 'jobs/' . uniqid('job_', true) . '.json';
        if (!is_dir(dirname($payloadFile))) {
            mkdir(dirname($payloadFile), 0755, true);
        }

        file_put_contents($payloadFile, json_encode(['script' => $script, 'arguments' => $arguments], JSON_UNESCAPED_UNICODE));

        $command = escapeshellcmd($this->phpBinary) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($payloadFile) . ' > /dev/null 2>&1 &';
        exec($command);

        return true;
    }

    /**
     * Read the payload from a background job file.
     * This helper can be used in the dispatched script itself.
     *
     * @param string $payloadFile The path to the JSON payload file.
     * @return array|null
     */
    public static function readPayload(string $payloadFile): ?array
    {
        if (!file_exists($payloadFile)) {
            return null;
        }
        $content = file_get_contents($payloadFile);
        return json_decode($content, true);
    }
}
