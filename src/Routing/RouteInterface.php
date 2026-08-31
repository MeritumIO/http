<?php

namespace Meritum\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Georgeff\Kernel\Contract\DebuggableInterface;

interface RouteInterface extends DebuggableInterface
{
    /**
     * Get the route identifier
     */
    public function getId(): string;

    /**
     * The HTTP methods this route matches
     *
     * @return non-empty-list<string>
     */
    public function getMethods(): array;

    /**
     * The route's registered path pattern
     */
    public function getPath(): string;

    /**
     * The registered handler: a RequestHandlerInterface instance, or a container service ID to resolve one from
     */
    public function getHandler(): RequestHandlerInterface|string;

    /**
     * Return a new instance with the given path arguments bound
     *
     * @param array<string, string> $arguments
     */
    public function withArguments(array $arguments): RouteInterface;

    /**
     * The path arguments bound to this route
     *
     * @return array<string, string>
     */
    public function getArguments(): array;

    /**
     * The value of a single bound path argument, or the given default if it's not set
     */
    public function getArgument(string $name, ?string $default = null): ?string;

    /**
     * Add middleware to run for this route, after the global stack and before the handler
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): static;

    /**
     * Whether this route has any middleware attached
     */
    public function hasMiddleware(): bool;

    /**
     * The middleware attached to this route
     *
     * @return iterable<int, MiddlewareInterface|string>
     */
    public function getMiddleware(): iterable;
}
