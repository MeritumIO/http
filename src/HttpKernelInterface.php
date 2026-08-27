<?php

namespace Meritum\Http;

use Georgeff\Kernel\KernelInterface;
use Psr\Http\Message\ResponseInterface;
use Meritum\Http\Routing\RouteInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Georgeff\Kernel\Contract\RunnableKernelInterface;

interface HttpKernelInterface extends RunnableKernelInterface, RequestHandlerInterface
{
    /**
     * @param string|non-empty-list<string> $methods
     */
    public function addRoute(array|string $methods, string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    public function get(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    public function post(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    public function put(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    public function patch(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    public function delete(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    public function options(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    public function head(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    /**
     * Add a middleware to the global stack
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): static;

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
}
