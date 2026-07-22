<?php

declare(strict_types=1);

namespace SwiftFuse\Foundation;

use Closure;
use RuntimeException;

/**
 * Minimal service container.
 *
 * Stores factory bindings and shared (singleton) instances keyed by an abstract
 * identifier — typically an interface name. This is the backbone of the
 * framework's modular architecture: developers can rebind any core service to
 * their own implementation via config/services.php without editing the core.
 */
class Container
{
    /**
     * Registered factory closures, keyed by abstract identifier.
     *
     * @var array<string, Closure>
     */
    protected array $bindings = [];

    /**
     * Resolved shared instances, keyed by abstract identifier.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Identifiers that must be resolved only once (shared).
     *
     * @var array<string, bool>
     */
    protected array $shared = [];

    /**
     * Register a binding in the container.
     *
     * @param string $abstract Identifier, usually an interface or alias.
     * @param Closure $factory Factory that receives the container and returns the service.
     * @param bool $shared Whether the resolved instance should be cached (singleton).
     * @return void
     */
    public function bind(string $abstract, Closure $factory, bool $shared = false): void
    {
        $this->bindings[$abstract] = $factory;
        $this->shared[$abstract] = $shared;
        unset($this->instances[$abstract]);
    }

    /**
     * Register a shared (singleton) binding.
     *
     * @param string $abstract Identifier, usually an interface or alias.
     * @param Closure $factory Factory that returns the service.
     * @return void
     */
    public function singleton(string $abstract, Closure $factory): void
    {
        $this->bind($abstract, $factory, true);
    }

    /**
     * Register an already-created instance as a shared binding.
     *
     * @param string $abstract Identifier.
     * @param mixed $instance The concrete instance to store.
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
        $this->shared[$abstract] = true;
    }

    /**
     * Resolve a service from the container.
     *
     * Falls back to instantiating $abstract directly when it is a concrete,
     * no-argument class name with no registered binding.
     *
     * @param string $abstract Identifier to resolve.
     * @return mixed The resolved service.
     *
     * @throws RuntimeException When the identifier cannot be resolved.
     */
    public function make(string $abstract): mixed
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $object = ($this->bindings[$abstract])($this);
            if ($this->shared[$abstract] ?? false) {
                $this->instances[$abstract] = $object;
            }
            return $object;
        }

        if (class_exists($abstract)) {
            return new $abstract();
        }

        throw new RuntimeException("Unable to resolve [{$abstract}] from the container.");
    }

    /**
     * Determine whether the container can resolve the given identifier.
     *
     * @param string $abstract Identifier.
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || array_key_exists($abstract, $this->instances)
            || class_exists($abstract);
    }
}
