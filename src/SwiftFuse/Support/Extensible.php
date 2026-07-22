<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

use BadMethodCallException;
use Closure;

/**
 * Extensible trait.
 *
 * Lets developers attach new methods to a framework class at runtime without
 * editing the class itself, e.g.:
 *
 *     Router::extend('apiResource', function (string $name) { ... });
 *     $router->apiResource('users');
 *
 * Registered closures are bound to the instance, so $this refers to the object.
 * This is SwiftFusePHP's take on a "macroable" object; the implementation is
 * original to this framework.
 */
trait Extensible
{
    /**
     * Registered extension closures, keyed by method name.
     *
     * @var array<string, callable>
     */
    protected static array $extensions = [];

    /**
     * Register a new runtime method for this class.
     *
     * @param string $name Method name to expose.
     * @param callable $callback Implementation invoked when the method is called.
     * @return void
     */
    public static function extend(string $name, callable $callback): void
    {
        static::$extensions[$name] = $callback;
    }

    /**
     * Determine whether an extension method has been registered.
     *
     * @param string $name Method name.
     * @return bool
     */
    public static function hasExtension(string $name): bool
    {
        return isset(static::$extensions[$name]);
    }

    /**
     * Handle dynamic instance calls to registered extensions.
     *
     * @param string $name Called method name.
     * @param array<int, mixed> $arguments Arguments passed to the method.
     * @return mixed
     *
     * @throws BadMethodCallException When no extension matches the method name.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (!static::hasExtension($name)) {
            throw new BadMethodCallException(sprintf('Method %s::%s() does not exist.', static::class, $name));
        }

        $callback = static::$extensions[$name];
        if ($callback instanceof Closure) {
            $callback = Closure::bind($callback, $this, static::class);
        }

        return $callback(...$arguments);
    }

    /**
     * Handle dynamic static calls to registered extensions.
     *
     * @param string $name Called method name.
     * @param array<int, mixed> $arguments Arguments passed to the method.
     * @return mixed
     *
     * @throws BadMethodCallException When no extension matches the method name.
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        if (!static::hasExtension($name)) {
            throw new BadMethodCallException(sprintf('Static method %s::%s() does not exist.', static::class, $name));
        }

        return (static::$extensions[$name])(...$arguments);
    }
}
