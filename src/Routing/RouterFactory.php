<?php

namespace Meritum\Http\Routing;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Meritum\Http\Exception\RouteCacheException;

final class RouterFactory
{
    /**
     * @param iterable<int, MiddlewareInterface|string> $middleware
     * @param \Closure(): ?string                       $routeCacheFile
     */
    public function __construct(
        private readonly iterable $middleware,
        private readonly RouteCollection $routes,
        private readonly \Closure $routeCacheFile
    ) {}

    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $cacheFile = ($this->routeCacheFile)();

        $dispatcher = null === $cacheFile
            ? \FastRoute\simpleDispatcher($this->getRouteDefinitionCallback())
            : $this->getCachedDispatcher($cacheFile);

        return new Router($this->middleware, $this->routes, $dispatcher, $container);
    }

    private function getCachedDispatcher(string $cacheFile): \FastRoute\Dispatcher
    {
        try {
            return \FastRoute\cachedDispatcher($this->getRouteDefinitionCallback(), ['cacheFile' => $cacheFile]);
        } catch (\Throwable $e) {
            RouteCacheException::throw($e->getMessage(), $e);
        }
    }

    private function getRouteDefinitionCallback(): callable
    {
        return function (\FastRoute\RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute($route->getMethods(), $route->getPath(), $route->getId());
            }
        };
    }
}
