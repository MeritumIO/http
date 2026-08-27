<?php

namespace Meritum\Http\Middleware;

use Traversable;
use ArrayIterator;
use IteratorAggregate;
use Psr\Http\Server\MiddlewareInterface;
use Georgeff\Kernel\Contract\DebuggableInterface;

/**
 * @internal
 *
 * @implements IteratorAggregate<int, MiddlewareInterface|string>
 */
final class MiddlewareStack implements IteratorAggregate, DebuggableInterface
{
    /**
     * @var array<int, MiddlewareInterface|string>
     */
    private array $middleware = [];

    public function getDebugInfo(): array
    {
        $data = [];

        foreach ($this->middleware as $middleware) {
            $data[] = is_string($middleware) ? $middleware : $middleware::class;
        }

        return $data;
    }

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
