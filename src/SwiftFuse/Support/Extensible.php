<?php

declare(strict_types=1);

namespace SwiftFuse\Support;

use BadMethodCallException;
use Closure;
use ReflectionFunction;

/**
 * Extensible trait.
 *
 * Lets developers attach new methods to a framework class at runtime without
 * editing the class itself, e.g.:
 *
 *     Router::extend('apiResource', function (string $name) { ... });
 *     $router->apiResource('users');
 *
 * An extension belongs to the class it was registered on: it is available on
 * that class and its subclasses, but not on parent, sibling or unrelated
 * classes. When a subclass registers a method with the same name, its own
 * version wins for it and its descendants.
 *
 * Registered closures are bound to the instance, so $this refers to the object.
 * This is SwiftFusePHP's take on a "macroable" object; the implementation is
 * original to this framework.
 */
trait Extensible
{
    /**
     * Registered extension callbacks, keyed by the class they were registered on, then by method name.
     *
     * @var array<class-string, array<string, callable>>
     */
    protected static array $extensions = [];

    /**
     * Register a new runtime method for this class and its subclasses.
     *
     * @param string $name Method name to expose.
     * @param callable $callback Implementation invoked when the method is called.
     * @return void
     */
    public static function extend(string $name, callable $callback): void
    {
        static::$extensions[static::class][$name] = $callback;
    }

    /**
     * Determine whether an extension method is available on this class, directly or through a parent class.
     *
     * @param string $name Method name.
     * @return bool
     */
    public static function hasExtension(string $name): bool
    {
        return self::resolveExtension($name) !== null;
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
        $callback = self::resolveExtension($name);
        if ($callback === null) {
            throw new BadMethodCallException(sprintf('Method %s::%s() does not exist.', static::class, $name));
        }

        if ($callback instanceof Closure) {
            // A static closure cannot receive $this; binding one to the instance would fail, so it only gets the scope.
            $callback = (new ReflectionFunction($callback))->isStatic()
                ? Closure::bind($callback, null, static::class)
                : Closure::bind($callback, $this, static::class);
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
        $callback = self::resolveExtension($name);
        if ($callback === null) {
            throw new BadMethodCallException(sprintf('Static method %s::%s() does not exist.', static::class, $name));
        }

        return $callback(...$arguments);
    }

    /**
     * Find the extension visible from the called class, from the class itself up to its ancestors.
     *
     * @param string $name Method name.
     * @return callable|null The nearest registered callback, or null when none is visible.
     */
    private static function resolveExtension(string $name): ?callable
    {
        for ($class = static::class; $class !== false; $class = get_parent_class($class)) {
            if (isset(static::$extensions[$class][$name])) {
                return static::$extensions[$class][$name];
            }
        }

        return null;
    }
}
