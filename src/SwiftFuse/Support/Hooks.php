<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

/**
 * Lightweight event/hook dispatcher.
 *
 * Provides named extension points throughout the request lifecycle so
 * developers can plug behavior in from app/bootstrap.php without modifying the
 * core. It generalizes the legacy beforeAction/afterAction controller hooks
 * into a framework-wide mechanism. An event is either fired, letting any
 * listener veto it, or filtered, passing a value through every listener so each
 * one can transform it. Listeners run by priority, highest first, and in
 * registration order within the same priority.
 *
 * Example:
 *     Hooks::on('request.before', fn ($action, $params) => ...);
 *     Hooks::fire('request.before', [$action, $params]);
 *
 *     Hooks::on('invoice.total', fn (float $total, string $currency) => round($total, 2), priority: 10);
 *     $total = Hooks::filter('invoice.total', $total, [$currency]);
 */
final class Hooks
{
    /**
     * Registered listeners grouped by event name, then by priority (highest first).
     *
     * @var array<string, array<int, array<int, callable>>>
     */
    private static array $listeners = [];

    /**
     * Register a listener for an event.
     *
     * @param string $event Event name, e.g. "request.before".
     * @param callable $listener Callback invoked when the event is fired or filtered.
     * @param int $priority Higher priorities run first; equal priorities keep registration order.
     * @return void
     */
    public static function on(string $event, callable $listener, int $priority = 0): void
    {
        self::$listeners[$event][$priority][] = $listener;
        krsort(self::$listeners[$event], SORT_NUMERIC);
    }

    /**
     * Fire an event, invoking every registered listener in priority order.
     *
     * If any listener returns false, the dispatch stops and false is returned.
     * This lets a hook veto an action (e.g. block a request).
     *
     * @param string $event Event name.
     * @param array<int, mixed> $arguments Arguments passed to each listener.
     * @return bool False when a listener vetoed the event, true otherwise.
     */
    public static function fire(string $event, array $arguments = []): bool
    {
        foreach (self::listenersFor($event) as $listener) {
            if ($listener(...$arguments) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pass a value through every listener of an event, in priority order.
     *
     * Each listener receives the current value followed by the given arguments
     * and returns the value handed to the next listener (return it unchanged to
     * leave it as is). Without listeners the value is returned untouched.
     *
     * @param string $event Event name, e.g. "controller.responding".
     * @param mixed $value The value to transform.
     * @param array<int, mixed> $arguments Context passed to each listener after the value.
     * @return mixed The value returned by the last listener.
     */
    public static function filter(string $event, mixed $value, array $arguments = []): mixed
    {
        foreach (self::listenersFor($event) as $listener) {
            $value = $listener($value, ...$arguments);
        }

        return $value;
    }

    /**
     * Determine whether any listener is registered for an event.
     *
     * @param string $event Event name.
     * @return bool
     */
    public static function has(string $event): bool
    {
        return !empty(self::$listeners[$event]);
    }

    /**
     * Get the listeners of an event in execution order.
     *
     * @param string $event Event name.
     * @return array<int, callable>
     */
    private static function listenersFor(string $event): array
    {
        return array_merge(...array_values(self::$listeners[$event] ?? []));
    }
}
