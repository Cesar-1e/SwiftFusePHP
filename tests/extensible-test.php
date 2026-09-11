<?php

/**
 * Extensible: extensions belong to the class they were registered on and are
 * inherited by its subclasses, never shared with unrelated classes.
 */

declare(strict_types=1);

use SwiftFuse\Http\Controller;
use SwiftFuse\Routing\Router;
use SwiftFuse\Tests\Fixtures\Extensible\BaseProbeController;
use SwiftFuse\Tests\Fixtures\Extensible\ChildProbeController;
use SwiftFuse\Tests\Fixtures\Extensible\SiblingProbeController;
use SwiftFuse\Tests\Support\TestRun;

require __DIR__ . '/bootstrap.php';

$test = new TestRun('Extensible: per-class registry with inheritance');

$test->test('Router and Controller extensions with the same name do not collide', function () use ($test): void {
    Router::extend('describe', static fn (): string => 'router');
    Controller::extend('describe', static fn (): string => 'controller');

    $test->assertSame('router', (new Router())->describe(), 'the router uses its own extension');
    $test->assertSame('controller', (new SiblingProbeController())->describe(), 'controllers use theirs');
});

$test->test('a base class extension is visible in its subclasses', function () use ($test): void {
    Controller::extend('fromBase', static fn (string $suffix): string => "base:{$suffix}");

    $test->assertTrue(ChildProbeController::hasExtension('fromBase'), 'hasExtension() sees the inherited extension');
    $test->assertSame('base:child', (new ChildProbeController())->fromBase('child'), '__call() resolves it');
    $test->assertSame(
        'base:sibling',
        (new SiblingProbeController())->fromBase('sibling'),
        'every subclass inherits it'
    );
});

$test->test('a subclass extension is hidden from its parent, siblings and others', function () use ($test): void {
    BaseProbeController::extend('probeOnly', static fn (): string => 'probe');

    $test->assertTrue(BaseProbeController::hasExtension('probeOnly'), 'the registering class sees it');
    $test->assertTrue(ChildProbeController::hasExtension('probeOnly'), 'its subclass sees it');
    $test->assertTrue(!Controller::hasExtension('probeOnly'), 'the parent does not see it');
    $test->assertTrue(!SiblingProbeController::hasExtension('probeOnly'), 'a sibling does not see it');
    $test->assertTrue(!Router::hasExtension('probeOnly'), 'an unrelated class does not see it');
    $test->assertThrows(
        BadMethodCallException::class,
        static fn (): mixed => (new SiblingProbeController())->probeOnly(),
        'calling it on a sibling fails'
    );
});

$test->test('the nearest registration wins when a subclass redefines an extension', function () use ($test): void {
    Controller::extend('greet', static fn (): string => 'base');
    BaseProbeController::extend('greet', static fn (): string => 'probe');

    $test->assertSame('probe', (new ChildProbeController())->greet(), 'the subclass registration wins below it');
    $test->assertSame('base', (new SiblingProbeController())->greet(), 'siblings keep the base registration');
});

$test->test('static calls resolve extensions through the same hierarchy', function () use ($test): void {
    Controller::extend('staticFromBase', static fn (): string => 'static-base');
    BaseProbeController::extend('staticFromProbe', static fn (): string => 'static-probe');

    $test->assertSame('static-base', ChildProbeController::staticFromBase(), 'an inherited static extension resolves');
    $test->assertSame('static-probe', ChildProbeController::staticFromProbe(), 'a parent registration resolves');
    $test->assertThrows(
        BadMethodCallException::class,
        static fn (): mixed => SiblingProbeController::staticFromProbe(),
        'a sibling registration does not resolve'
    );
});

$test->test('closures are bound to the called instance', function () use ($test): void {
    Controller::extend('describeInstance', function (): string {
        return static::class . ':' . ($this instanceof ChildProbeController ? 'child' : 'other');
    });

    $test->assertSame(
        ChildProbeController::class . ':child',
        (new ChildProbeController())->describeInstance(),
        '$this and static refer to the called instance'
    );
});

$test->test('static closures work as instance extensions', function () use ($test): void {
    Router::extend('staticHelper', static fn (string $to): string => "to:{$to}");

    $test->assertSame('to:login', (new Router())->staticHelper('login'), 'a static closure is called without $this');
});

$test->test('unknown methods still throw BadMethodCallException', function () use ($test): void {
    $exception = $test->assertThrows(
        BadMethodCallException::class,
        static fn (): mixed => (new Router())->missingExtension(),
        'an unknown instance method throws'
    );

    $test->assertTrue(
        $exception !== null && str_contains($exception->getMessage(), Router::class . '::missingExtension()'),
        'the message names the class and the method'
    );
});

$test->finish();
