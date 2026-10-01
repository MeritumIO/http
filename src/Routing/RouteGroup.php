<?php

namespace Meritum\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;
use Meritum\Http\Middleware\MiddlewareStack;

/**
 * @internal
 */
final class RouteGroup implements RouteGroupInterface
{
    private readonly string $prefix;

    /**
     * @var callable(RouteRegistrationInterface): void
     */
    private $callback;

    private readonly MiddlewareStack $middleware;

    /**
     * @param callable(RouteRegistrationInterface): void $callback
     */
    public function __construct(
        string $prefix,
        callable $callback,
        private readonly RouteRegistrationInterface $routes
    ) {
        $this->prefix   = $prefix;
        $this->callback = $callback;

        $this->middleware = new MiddlewareStack();
    }

    public function getDebugInfo(): array
    {
        return [
            'prefix'     => $this->getPrefix(),
            'middleware' => $this->middleware->getDebugInfo(),
        ];
    }

    public function invokeCallback(): self
    {
        ($this->callback)($this->routes);

        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): static
    {
        $this->middleware->add($middleware);

        return $this;
    }

    public function getMiddlewareStack(): MiddlewareStack
    {
        return $this->middleware;
    }
}
