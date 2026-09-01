<?php

namespace Meritum\Http\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterFactory
{
    /**
     * @param iterable<int, MiddlewareInterface|string> $middleware
     */
    public function __construct(
        private readonly iterable $middleware,
        private readonly RouteCollection $routes
    ) {}

    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $dispatcher = \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute($route->getMethods(), $route->getPath(), $route->getId());
            }
        });

        return new Router($this->middleware, $this->routes, $dispatcher, $container);
    }
}
