<?php

namespace Meritum\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface RouteInterface
{
    /**
     * @return non-empty-list<string>
     */
    public function getMethods(): array;

    public function getPath(): string;

    public function getHandler(): RequestHandlerInterface|string;

    /**
     * @param array<string, string> $arguments
     */
    public function withArguments(array $arguments): RouteInterface;

    /**
     * @return array<string, string>
     */
    public function getArguments(): array;

    public function getArgument(string $name, ?string $default = null): ?string;

    public function addMiddleware(MiddlewareInterface|string $middleware): static;

    public function hasMiddleware(): bool;

    /**
     * @return iterable<int, MiddlewareInterface|string>
     */
    public function getMiddleware(): iterable;
}
