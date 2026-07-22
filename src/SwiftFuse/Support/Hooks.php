<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

/**
 * Lightweight event/hook dispatcher.
 *
 * Provides named extension points throughout the request lifecycle so
 * developers can plug behavior in from app/bootstrap.php without modifying the
 * core. It generalizes the legacy beforeAction/afterAction controller hooks
 * into a framework-wide mechanism.
 *
 * Example:
 *     Hooks::on('request.before', fn ($action, $params) => ...);
 *     Hooks::fire('request.before', [$action, $params]);
 */
final class Hooks
{
    /**
     * Registered listeners grouped by event name.
     *
     * @var array<string, array<int, callable>>
     */
    private static array $listeners = [];

    /**
     * Register a listener for an event.
     *
     * @param string $event Event name, e.g. "request.before".
     * @param callable $listener Callback invoked when the event fires.
     * @return void
     */
    public static function on(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    /**
     * Fire an event, invoking every registered listener in order.
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
        foreach (self::$listeners[$event] ?? [] as $listener) {
            if ($listener(...$arguments) === false) {
                return false;
            }
        }

        return true;
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
}
