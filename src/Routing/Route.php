<?php

namespace Meritum\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Meritum\Http\Middleware\MiddlewareStack;

/**
 * @internal
 */
final class Route implements RouteInterface
{
    /**
     * @var non-empty-list<string>
     */
    private readonly array $methods;

    private readonly string $path;

    /**
     * @var array<string, string>
     */
    private array $arguments = [];

    private readonly MiddlewareStack $middleware;

    /**
     * @param non-empty-list<string> $methods
     */
    public function __construct(
        array $methods,
        string $path,
        private readonly RequestHandlerInterface|string $handler
    ) {
        $this->methods = array_map('strtoupper', $methods);

        if ('' === $path || '/' !== $path[0]) {
            $path = '/' . $path;
        }

        $this->path = $path;

        $this->middleware = new MiddlewareStack();
    }

    public function __clone(): void
    {
        $this->middleware = clone $this->middleware;
    }

    public function getDebugInfo(): array
    {
        return [
            'methods'    => $this->methods,
            'path'       => $this->path,
            'handler'    => is_string($this->handler) ? $this->handler : $this->handler::class,
            'arguments'  => $this->arguments,
            'middleware' => $this->middleware->getDebugInfo(),
        ];
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): RequestHandlerInterface|string
    {
        return $this->handler;
    }

    public function withArguments(array $arguments): RouteInterface
    {
        $route = clone $this;

        $route->arguments = $arguments;

        return $route;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getArgument(string $name, ?string $default = null): ?string
    {
        return $this->arguments[$name] ?? $default;
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): static
    {
        $this->middleware->add($middleware);

        return $this;
    }

    public function hasMiddleware(): bool
    {
        return false === $this->middleware->isEmpty();
    }

    public function getMiddleware(): iterable
    {
        return $this->middleware;
    }
}
