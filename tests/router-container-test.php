<?php

/**
 * Router: controllers are resolved through the application container when an
 * application is bootstrapped, and instantiated directly otherwise.
 */

declare(strict_types=1);

use App\Controllers\ConventionProbeController;
use SwiftFuse\Foundation\Application;
use SwiftFuse\Foundation\ErrorHandler;
use SwiftFuse\Http\Request;
use SwiftFuse\Routing\Router;
use SwiftFuse\Tests\Fixtures\DecoratedRecordingController;
use SwiftFuse\Tests\Fixtures\RecordingController;
use SwiftFuse\Tests\Support\TestRun;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Fixtures/App/Controllers/ConventionProbeController.php';

$test = new TestRun('Router: controllers resolved through the container');

$router = new Router();
$router->get('plain/{value}', [RecordingController::class, 'plain']);

$test->test('without an application, explicit routes instantiate the controller class', function () use (
    $test,
    $router
): void {
    $test->assertTrue(!Application::hasInstance(), 'no application is bootstrapped');
    RecordingController::$events = [];

    $router->dispatch(new Request(['plain', '1']));

    $test->assertSame(
        'action:plain:' . RecordingController::class . ':1',
        RecordingController::$events[1] ?? null,
        'the declared class handled the route'
    );
});

$test->test('without an application, convention routing instantiates the controller class', function () use (
    $test,
    $router
): void {
    ConventionProbeController::$events = [];

    $router->dispatch(new Request(['conventionProbe', 'ping', '1']));

    $test->assertSame(['ping:1'], ConventionProbeController::$events, 'the convention controller handled the route');
});

$application = new Application(new ErrorHandler(false, sys_get_temp_dir() . '/swiftfuse-router-container-test.log'));

$test->test('with an application but no binding, the declared classes still handle the routes', function () use (
    $test,
    $router
): void {
    $test->assertTrue(Application::hasInstance(), 'the application is bootstrapped');
    RecordingController::$events = [];
    ConventionProbeController::$events = [];

    $router->dispatch(new Request(['plain', '2']));
    $router->dispatch(new Request(['conventionProbe', 'ping', '2']));

    $test->assertSame(
        'action:plain:' . RecordingController::class . ':2',
        RecordingController::$events[1] ?? null,
        'the explicit route used the declared class'
    );
    $test->assertSame(['ping:2'], ConventionProbeController::$events, 'the convention route used the declared class');
});

$test->test('a container binding decorates the controller of an explicit route', function () use (
    $test,
    $router,
    $application
): void {
    $application->bind(
        RecordingController::class,
        static fn (): RecordingController => new DecoratedRecordingController()
    );
    RecordingController::$events = [];

    $router->dispatch(new Request(['plain', '3']));

    $test->assertSame(
        [
            'before:plain',
            'decorated:before-plain',
            'action:plain:' . DecoratedRecordingController::class . ':3',
            'after:plain',
        ],
        RecordingController::$events,
        'the decorator ran the whole lifecycle around the original action'
    );
});

$test->test('a container binding replaces the controller of a convention route', function () use (
    $test,
    $router,
    $application
): void {
    $application->bind(
        ConventionProbeController::class,
        static fn (): RecordingController => new RecordingController()
    );
    RecordingController::$events = [];
    ConventionProbeController::$events = [];

    $router->dispatch(new Request(['conventionProbe', 'plain', '4']));

    $test->assertSame([], ConventionProbeController::$events, 'the replaced controller did not run');
    $test->assertSame(
        'action:plain:' . RecordingController::class . ':4',
        RecordingController::$events[1] ?? null,
        'the bound controller handled the route'
    );
});

$test->test('an instance registered in the container is the one the router uses', function () use (
    $test,
    $router,
    $application
): void {
    $instance = new RecordingController();
    $application->instance(RecordingController::class, $instance);

    $router->dispatch(new Request(['plain', '5']));

    $test->assertSame($instance, RecordingController::$lastInstance, 'the registered instance handled the route');
});

$test->test('a binding that does not return a controller is rejected with a clear exception', function () use (
    $test,
    $router,
    $application
): void {
    $application->bind(RecordingController::class, static fn (): stdClass => new stdClass());

    $exception = $test->assertThrows(
        UnexpectedValueException::class,
        static fn () => $router->dispatch(new Request(['plain', '6'])),
        'the dispatch fails before running anything'
    );

    $test->assertTrue(
        $exception !== null
            && str_contains($exception->getMessage(), RecordingController::class)
            && str_contains($exception->getMessage(), 'stdClass'),
        'the message names the class and what the binding returned'
    );
});

$test->finish();
