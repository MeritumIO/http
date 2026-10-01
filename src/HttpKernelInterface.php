<?php

namespace Meritum\Http;

use Georgeff\Kernel\KernelInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Meritum\Http\Routing\RouteInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Meritum\Http\Routing\RouteRegistrationInterface;
use Meritum\Http\Contract\ExceptionHandlerInterface;
use Georgeff\Kernel\Contract\RunnableKernelInterface;

interface HttpKernelInterface extends RunnableKernelInterface, RequestHandlerInterface, RouteRegistrationInterface
{
    /**
     * Enable the route cache
     */
    public function enableRouteCache(string $file): static;

    /**
     * Add a middleware to the global stack
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): static;

    /**
     * Add an exception handler
     *
     * @param callable(ContainerInterface $container): ExceptionHandlerInterface $factory
     */
    public function addExceptionHandler(callable $factory): static;

    /**
     * Register a terminating callback
     *
     * @param callable(ServerRequestInterface, ResponseInterface, KernelInterface): void $callback
     */
    public function onTerminating(callable $callback): static;

    /**
     * Terminate a request/response cycle
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void;

    /**
     * Get registered routes, keyed by route ID
     *
     * @return iterable<string, RouteInterface>
     */
    public function getRoutes(): iterable;
}
