<?php

/**
 * Controller lifecycle: the after-hook runs once with or without json(), and the
 * "controller.responding" filter shapes JSON payloads with the dispatch context.
 */

declare(strict_types=1);

use App\Controllers\ConventionProbeController;
use SwiftFuse\Http\HttpException;
use SwiftFuse\Http\Request;
use SwiftFuse\Routing\Router;
use SwiftFuse\Support\Config;
use SwiftFuse\Support\Hooks;
use SwiftFuse\Tests\Fixtures\JsonProbeController;
use SwiftFuse\Tests\Fixtures\RecordingController;
use SwiftFuse\Tests\Support\PhpProcess;
use SwiftFuse\Tests\Support\TestRun;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Fixtures/App/Controllers/ConventionProbeController.php';

$test = new TestRun('Controller lifecycle and JSON responses');

$afterEvents = new ArrayObject();
Hooks::on('controller.after', static fn (string $action) => $afterEvents->append($action));

$router = new Router();
$router->get('plain/{value}', [RecordingController::class, 'plain']);
$router->get('forbidden', [RecordingController::class, 'forbidden']);

foreach ([false, true] as $jsonLifecycle) {
    $mode = $jsonLifecycle ? 'on' : 'off';

    $test->test("the after-hook runs once when the action returns (json_lifecycle {$mode})", function () use (
        $test,
        $router,
        $afterEvents,
        $jsonLifecycle
    ): void {
        Config::set('app.json_lifecycle', $jsonLifecycle);
        RecordingController::$events = [];
        $afterEvents->exchangeArray([]);

        $router->dispatch(new Request(['plain', '7']));

        $test->assertSame(
            ['before:plain', 'action:plain:' . RecordingController::class . ':7', 'after:plain'],
            RecordingController::$events,
            'before, action and after ran once, in order'
        );
        $test->assertSame(['plain'], $afterEvents->getArrayCopy(), '"controller.after" fired once');
    });
}

$test->test('a vetoed action runs neither the action nor the after-hook', function () use (
    $test,
    $router,
    $afterEvents
): void {
    Config::set('app.json_lifecycle', true);
    RecordingController::$events = [];
    $afterEvents->exchangeArray([]);

    $exception = $test->assertThrows(
        HttpException::class,
        static fn () => $router->dispatch(new Request(['forbidden'])),
        'the dispatch is blocked'
    );

    $test->assertSame(403, $exception?->getStatusCode(), 'with status 403');
    $test->assertSame(['before:forbidden'], RecordingController::$events, 'only the before-hook ran');
    $test->assertSame([], $afterEvents->getArrayCopy(), '"controller.after" did not fire');
});

$test->test('convention routing never dispatches the controller lifecycle methods', function () use ($test): void {
    ConventionProbeController::$events = [];
    $router = new Router();

    foreach (['enterAction', 'leaveAction', 'LEAVEACTION'] as $segment) {
        $router->dispatch(new Request(['conventionProbe', $segment]));
    }
    $router->dispatch(new Request(['conventionProbe', 'ping', '5']));

    $test->assertSame(
        ['index:enterAction', 'index:leaveAction', 'index:LEAVEACTION', 'ping:5'],
        ConventionProbeController::$events,
        'lifecycle names fall back to index() while regular actions still resolve'
    );
});

/**
 * Dispatch a path in tests/Fixtures/json-dispatch.php and parse what it reported.
 *
 * @param string $path Route path, or "standalone" to call json() outside a dispatch.
 * @param bool $jsonLifecycle Value of app.json_lifecycle in the child process.
 * @return array{exitCode: int, body: mixed, after: array<int, string>, responding: array<int, string>}
 */
$runJson = static function (string $path, bool $jsonLifecycle): array {
    $result = PhpProcess::run(__DIR__ . '/Fixtures/json-dispatch.php', [$path, $jsonLifecycle ? 'on' : 'off']);
    $lines = preg_split('/\R/', $result['stderr']) ?: [];
    $linesStartingWith = static fn (string $prefix): array => array_values(
        array_filter($lines, static fn (string $line): bool => str_starts_with($line, $prefix))
    );

    return [
        'exitCode' => $result['exitCode'],
        'body' => json_decode($result['stdout'], true),
        'after' => $linesStartingWith('after:'),
        'responding' => $linesStartingWith('responding:'),
    ];
};

$probeClass = JsonProbeController::class;

$test->test('with json_lifecycle on, json() runs the after-hook exactly once', function () use (
    $test,
    $runJson
): void {
    $run = $runJson('respond/42', true);

    $test->assertSame(0, $run['exitCode'], 'the process ended through json()');
    $test->assertSame(['after:respond'], $run['after'], '"controller.after" fired once');
    $test->assertSame(['id' => '42', 'decorated' => true], $run['body'], 'the filtered payload was sent');
});

$test->test('with json_lifecycle off, json() keeps skipping the after-hook', function () use (
    $test,
    $runJson
): void {
    $run = $runJson('respond/42', false);

    $test->assertSame(0, $run['exitCode'], 'the process ended through json()');
    $test->assertSame([], $run['after'], '"controller.after" did not fire, as in earlier versions');
    $test->assertSame(['id' => '42', 'decorated' => true], $run['body'], '"controller.responding" still applies');
});

$test->test('"controller.responding" receives the payload, status, controller, action and params', function () use (
    $test,
    $runJson,
    $probeClass
): void {
    $run = $runJson('respond/42', true);

    $test->assertSame(
        ["responding:respond:201:{$probeClass}:42"],
        $run['responding'],
        'the filter ran once with the dispatch context the router provided'
    );
});

$test->test('json() from the before-hook never runs the after-hook', function () use (
    $test,
    $runJson,
    $probeClass
): void {
    $run = $runJson('blocked', true);

    $test->assertSame([], $run['after'], 'the action never started, so "controller.after" did not fire');
    $test->assertSame(["responding:blocked:401:{$probeClass}:"], $run['responding'], 'the filter knows the action');
    $test->assertSame(['error' => 'denied', 'decorated' => true], $run['body'], 'the rejection was sent');
});

foreach ([true, false] as $jsonLifecycle) {
    $mode = $jsonLifecycle ? 'on' : 'off';

    $test->test("json() from the after-hook cannot run it twice (json_lifecycle {$mode})", function () use (
        $test,
        $runJson,
        $jsonLifecycle
    ): void {
        $run = $runJson('answer-from-after', $jsonLifecycle);

        $test->assertSame(['after:answerFromAfter'], $run['after'], '"controller.after" fired once');
        $test->assertSame(
            ['from' => 'after', 'decorated' => true],
            $run['body'],
            'the response of the after-hook was sent'
        );
    });
}

$test->test('json() outside a router dispatch reports no action and runs no after-hook', function () use (
    $test,
    $runJson,
    $probeClass
): void {
    $run = $runJson('standalone', true);

    $test->assertSame(["responding:null:200:{$probeClass}:"], $run['responding'], 'the action is null');
    $test->assertSame([], $run['after'], '"controller.after" did not fire');
    $test->assertSame(['standalone' => true, 'decorated' => true], $run['body'], 'the payload was sent');
});

$test->finish();
