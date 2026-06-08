<?php

namespace Meritum\Http\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterFactory
{
    /**
     * @param iterable<int, MiddlewareInterface|string> $middleware
     * @param iterable<int, RouteInterface> $routes
     */
    public function __construct(
        private readonly iterable $middleware,
        private readonly iterable $routes
    ) {}

    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $dispatcher = \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute($route->getMethods(), $route->getPath(), $route);
            }
        });

        return new Router($this->middleware, $dispatcher, $container);
    }
}
