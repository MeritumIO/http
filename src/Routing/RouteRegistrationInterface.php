<?php

namespace Meritum\Http\Routing;

use Psr\Http\Server\RequestHandlerInterface;

interface RouteRegistrationInterface
{
    /**
     * Register a route group
     *
     * @param callable(RouteRegistrationInterface): void $callback
     */
    public function group(string $prefix, callable $callback): RouteGroupInterface;

    /**
     * Register a route matching the given HTTP method(s)
     *
     * @param string|non-empty-list<string> $methods
     */
    public function addRoute(array|string $methods, string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    /**
     * Register a route matching GET requests
     */
    public function get(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    /**
     * Register a route matching POST requests
     */
    public function post(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    /**
     * Register a route matching PUT requests
     */
    public function put(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    /**
     * Register a route matching PATCH requests
     */
    public function patch(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    /**
     * Register a route matching DELETE requests
     */
    public function delete(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    /**
     * Register a route matching OPTIONS requests
     */
    public function options(string $uri, RequestHandlerInterface|string $handler): RouteInterface;

    /**
     * Register a route matching HEAD requests
     */
    public function head(string $uri, RequestHandlerInterface|string $handler): RouteInterface;
}
