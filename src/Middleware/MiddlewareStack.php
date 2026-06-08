<?php

namespace Meritum\Http\Middleware;

use Traversable;
use ArrayIterator;
use IteratorAggregate;
use Psr\Http\Server\MiddlewareInterface;

/**
 * @internal
 *
 * @implements IteratorAggregate<int, MiddlewareInterface|string>
 */
final class MiddlewareStack implements IteratorAggregate
{
    /**
     * @var array<int, MiddlewareInterface|string>
     */
    private array $middleware = [];

    public function add(MiddlewareInterface|string $middleware): static
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    public function isEmpty(): bool
    {
        return [] === $this->middleware;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->middleware);
    }
}
