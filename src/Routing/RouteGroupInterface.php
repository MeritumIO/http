<?php

namespace Meritum\Http\Routing;

use Psr\Http\Server\MiddlewareInterface;

interface RouteGroupInterface
{
    /**
     * Get the group prefix
     */
    public function getPrefix(): string;

    /**
     * Add a group level middleware
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): static;
}
