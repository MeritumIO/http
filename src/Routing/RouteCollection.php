<?php

namespace Meritum\Http\Routing;

use Traversable;
use ArrayIterator;
use IteratorAggregate;
use Psr\Http\Server\RequestHandlerInterface;
use Georgeff\Kernel\Contract\DebuggableInterface;

/**
 * @internal
 *
 * @implements IteratorAggregate<int, RouteInterface>
 */
final class RouteCollection implements IteratorAggregate, DebuggableInterface
{
    /**
     * @var array<int, RouteInterface>
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
        return $this->routes[] = new Route($methods, $path, $handler, $this->activeGroups);
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
