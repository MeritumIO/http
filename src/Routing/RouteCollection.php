<?php

namespace Meritum\Http\Routing;

use Traversable;
use ArrayIterator;
use IteratorAggregate;
use Meritum\Http\Exception\RoutingException;
use Psr\Http\Server\RequestHandlerInterface;
use Georgeff\Kernel\Contract\DebuggableInterface;

/**
 * @internal
 *
 * @implements IteratorAggregate<string, RouteInterface>
 */
final class RouteCollection implements IteratorAggregate, DebuggableInterface
{
    /**
     * @var array<string, RouteInterface>
     */
    private array $routes = [];

    /**
     * @var RouteGroup[]
     */
    private array $activeGroups = [];

    public function group(string $prefix, callable $callback): RouteGroupInterface
    {
        $group = new RouteGroup($prefix, $callback, new GroupRouteRegister($this, $prefix));

        $this->activeGroups[] = $group;

        try {
            $group->invokeCallback();
        } finally {
            array_pop($this->activeGroups);
        }

        return $group;
    }

    /**
     * @param non-empty-list<string> $methods
     */
    public function add(array $methods, string $path, RequestHandlerInterface|string $handler): RouteInterface
    {
        $route = new Route($methods, $path, $handler, $this->activeGroups);

        RoutingException::throwIf(
            isset($this->routes[$route->getId()]),
            sprintf(
                'Duplicate route %s %s',
                implode('|', $methods),
                $path
            )
        );

        return $this->routes[$route->getId()] = $route;
    }

    public function get(string $id): RouteInterface
    {
        $route = $this->routes[$id] ?? null;

        RoutingException::throwIf(null === $route, "Route with ID [$id] was not found");

        /** @var RouteInterface $route */
        return $route;
    }

    public function isEmpty(): bool
    {
        return [] === $this->routes;
    }

    public function getDebugInfo(): array
    {
        $data = [];

        foreach ($this->routes as $route) {
            $data[] = $route->getDebugInfo();
        }

        return $data;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->routes);
    }
}
