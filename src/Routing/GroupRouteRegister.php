<?php

namespace Meritum\Http\Routing;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 */
final class GroupRouteRegister implements RouteRegistrationInterface
{
    public function __construct(private readonly RouteCollection $collection, private readonly string $groupPrefix) {}

    public function group(string $prefix, callable $callback): RouteGroupInterface
    {
        if ('' === $prefix || '/' !== $prefix[0]) {
            $prefix = '/' . $prefix;
        }

        $prefix = $this->groupPrefix . $prefix;

        return $this->collection->group($prefix, $callback);
    }

    public function addRoute(array|string $methods, string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        if ('' === $uri || '/' !== $uri[0]) {
            $uri = '/' . $uri;
        }

        $methods = is_string($methods) ? [$methods] : $methods;

        $path = $this->groupPrefix . $uri;

        return $this->collection->add($methods, $path, $handler);
    }

    public function get(string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        return $this->addRoute(['GET'], $uri, $handler);
    }

    public function post(string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        return $this->addRoute(['POST'], $uri, $handler);
    }

    public function put(string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        return $this->addRoute(['PUT'], $uri, $handler);
    }

    public function patch(string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        return $this->addRoute(['PATCH'], $uri, $handler);
    }

    public function delete(string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        return $this->addRoute(['DELETE'], $uri, $handler);
    }

    public function options(string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        return $this->addRoute(['OPTIONS'], $uri, $handler);
    }

    public function head(string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        return $this->addRoute(['HEAD'], $uri, $handler);
    }
}
