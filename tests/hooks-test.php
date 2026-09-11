<?php

/**
 * Hooks: listener priorities, value filters and the unchanged veto semantics of fire().
 */

declare(strict_types=1);

use SwiftFuse\Support\Hooks;
use SwiftFuse\Tests\Support\TestRun;

require __DIR__ . '/bootstrap.php';

$test = new TestRun('Hooks: priorities, filters and veto');

$test->test('without priorities, listeners run in registration order', function () use ($test): void {
    $calls = new ArrayObject();
    Hooks::on('order.default', static fn () => $calls->append('first'));
    Hooks::on('order.default', static fn () => $calls->append('second'));
    Hooks::on('order.default', static fn () => $calls->append('third'));

    $test->assertTrue(Hooks::fire('order.default'), 'fire() returns true when no listener vetoes');
    $test->assertSame(['first', 'second', 'third'], $calls->getArrayCopy(), 'listeners ran in registration order');
});

$test->test('higher priorities run first and equal priorities keep registration order', function () use ($test): void {
    $calls = new ArrayObject();
    Hooks::on('order.priority', static fn () => $calls->append('default-1'));
    Hooks::on('order.priority', static fn () => $calls->append('low'), -10);
    Hooks::on('order.priority', static fn () => $calls->append('high-1'), 10);
    Hooks::on('order.priority', static fn () => $calls->append('default-2'));
    Hooks::on('order.priority', static fn () => $calls->append('high-2'), 10);
    Hooks::on('order.priority', static fn () => $calls->append('highest'), 100);

    Hooks::fire('order.priority');

    $test->assertSame(
        ['highest', 'high-1', 'high-2', 'default-1', 'default-2', 'low'],
        $calls->getArrayCopy(),
        'listeners ran by descending priority, stable within a priority'
    );
});

$test->test('fire() still stops at the first listener that returns false', function () use ($test): void {
    $calls = new ArrayObject();
    Hooks::on('veto.check', static function () use ($calls): bool {
        $calls->append('allow');
        return true;
    });
    Hooks::on('veto.check', static function () use ($calls): bool {
        $calls->append('deny');
        return false;
    });
    Hooks::on('veto.check', static fn () => $calls->append('never'));

    $test->assertSame(false, Hooks::fire('veto.check', ['argument']), 'fire() reports the veto');
    $test->assertSame(['allow', 'deny'], $calls->getArrayCopy(), 'listeners after the veto did not run');
    $test->assertTrue(Hooks::fire('veto.unregistered'), 'an event without listeners is not vetoed');
});

$test->test('a high-priority listener can veto before lower-priority listeners run', function () use ($test): void {
    $calls = new ArrayObject();
    Hooks::on('veto.priority', static fn () => $calls->append('default'));
    Hooks::on('veto.priority', static function () use ($calls): bool {
        $calls->append('guard');
        return false;
    }, 50);

    $test->assertSame(false, Hooks::fire('veto.priority'), 'the guard vetoed the event');
    $test->assertSame(['guard'], $calls->getArrayCopy(), 'the default-priority listener did not run');
});

$test->test('fire() passes its arguments to every listener', function () use ($test): void {
    $received = new ArrayObject();
    Hooks::on('arguments.fire', static fn (string $action, array $params) => $received->append([$action, $params]));

    Hooks::fire('arguments.fire', ['show', ['42']]);

    $test->assertSame([['show', ['42']]], $received->getArrayCopy(), 'the listener received the arguments in order');
});

$test->test('filter() passes the value through the listeners by priority', function () use ($test): void {
    Hooks::on('price.total', static fn (float $total, float $taxRate): float => $total * $taxRate);
    Hooks::on('price.total', static fn (float $total): float => $total + 10.0, 5);
    Hooks::on('price.total', static fn (float $total): float => round($total, 2), -5);

    $test->assertSame(130.9, Hooks::filter('price.total', 100.0, [1.19]), '(100 + 10) * 1.19, then rounded');
});

$test->test('filter() hands each listener the value followed by the arguments', function () use ($test): void {
    Hooks::on('arguments.filter', static fn (array $value, string $first, int $second): array => [
        ...$value,
        $first,
        $second,
    ]);

    $test->assertSame(
        ['start', 'context', 2],
        Hooks::filter('arguments.filter', ['start'], ['context', 2]),
        'the listener received the value, then the arguments'
    );
});

$test->test('filter() returns the value untouched when no listener is registered', function () use ($test): void {
    $payload = ['ok' => true];

    $test->assertSame($payload, Hooks::filter('filter.unregistered', $payload, [200]), 'the value is unchanged');
});

$test->test('has() reports whether an event has listeners', function () use ($test): void {
    $test->assertTrue(!Hooks::has('has.check'), 'no listener before registration');

    Hooks::on('has.check', static fn (): bool => true, 3);

    $test->assertTrue(Hooks::has('has.check'), 'a listener registered with a priority counts');
});

$test->finish();
