<?php

declare(strict_types=1);

namespace SwiftFuse\Foundation;

use SwiftFuse\Http\HttpException;
use SwiftFuse\Http\Request;
use SwiftFuse\Routing\Router;
use Throwable;

/**
 * The application kernel.
 *
 * Acts as the service container and orchestrates the HTTP request lifecycle:
 * capture the request, dispatch it through the router, and convert any
 * HttpException (or unexpected error) into a rendered error response. This is
 * the English, object-oriented successor to the legacy index.php + Core.php
 * bootstrapping.
 */
final class Application extends Container
{
    /**
     * The shared application instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * The error handler responsible for rendering failures.
     *
     * @var ErrorHandler
     */
    private ErrorHandler $errorHandler;

    /**
     * @param ErrorHandler $errorHandler Handler used to render errors.
     */
    public function __construct(ErrorHandler $errorHandler)
    {
        $this->errorHandler = $errorHandler;
        $this->instance($this::class, $this);
        self::$instance = $this;
    }

    /**
     * Get the shared application instance.
     *
     * @return self
     *
     * @throws \RuntimeException When the application has not been bootstrapped.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The application has not been bootstrapped.');
        }

        return self::$instance;
    }

    /**
     * Register service bindings from a configuration map.
     *
     * Each entry maps an abstract identifier to a factory closure, enabling
     * developers to swap core services for their own implementations.
     *
     * @param array<string, \Closure> $services Map of identifier to factory.
     * @return void
     */
    public function registerServices(array $services): void
    {
        foreach ($services as $abstract => $factory) {
            $this->singleton($abstract, $factory);
        }
    }

    /**
     * Handle the current HTTP request and emit the response.
     *
     * @return void
     */
    public function run(): void
    {
        try {
            $request = Request::capture();
            /** @var Router $router */
            $router = $this->make(Router::class);
            $router->dispatch($request);
        } catch (HttpException $exception) {
            $this->errorHandler->renderHttpException($exception);
        } catch (Throwable $exception) {
            $this->errorHandler->renderThrowable($exception);
        }
    }
}
