<?php

/**
 * Subprocess fixture: dispatch a route whose response comes from json().
 *
 * Usage: php json-dispatch.php <path|standalone> <on|off>
 *
 * The second argument sets app.json_lifecycle. The JSON body goes to STDOUT;
 * each "controller.after" and "controller.responding" call is reported on
 * STDERR as one "after:" or "responding:" line. json() ends the process, so
 * reaching the end of this script means no JSON response was sent (exit code 3).
 */

declare(strict_types=1);

use SwiftFuse\Http\Request;
use SwiftFuse\Routing\Router;
use SwiftFuse\Support\Config;
use SwiftFuse\Support\Hooks;
use SwiftFuse\Tests\Fixtures\JsonProbeController;

require dirname(__DIR__) . '/bootstrap.php';

$path = $argv[1] ?? '';
Config::set('app.json_lifecycle', ($argv[2] ?? 'off') === 'on');

Hooks::on('controller.after', static function (string $action): void {
    fwrite(STDERR, "after:{$action}" . PHP_EOL);
});

Hooks::on(
    'controller.responding',
    static function (mixed $payload, int $status, object $controller, ?string $action, array $params): mixed {
        fwrite(STDERR, sprintf(
            'responding:%s:%d:%s:%s%s',
            $action ?? 'null',
            $status,
            $controller::class,
            implode(',', $params),
            PHP_EOL
        ));

        return is_array($payload) ? $payload + ['decorated' => true] : $payload;
    }
);

if ($path === 'standalone') {
    (new JsonProbeController())->respondDirectly();
}

$router = new Router();
$router->get('respond/{id}', [JsonProbeController::class, 'respond']);
$router->get('blocked', [JsonProbeController::class, 'blocked']);
$router->get('answer-from-after', [JsonProbeController::class, 'answerFromAfter']);
$router->dispatch(new Request(explode('/', $path)));

fwrite(STDERR, 'The dispatch returned without a JSON response.' . PHP_EOL);
exit(3);
