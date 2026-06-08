<?php

namespace Meritum\Http\Routing;

use Traversable;
use ArrayIterator;
use IteratorAggregate;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 *
 * @implements IteratorAggregate<int, RouteInterface>
 */
final class RouteCollection implements IteratorAggregate
{
    /**
     * @var array<int, RouteInterface>
     */
    private array $routes = [];

    /**
     * @param non-empty-list<string> $methods
     */
    public function add(array $methods, string $path, RequestHandlerInterface|string $handler): RouteInterface
    {
        return $this->routes[] = new Route($methods, $path, $handler);
    }

    public function isEmpty(): bool
    {
        return [] === $this->routes;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->routes);
    }
}
