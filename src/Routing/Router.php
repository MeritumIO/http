<?php

namespace Meritum\Http\Routing;

use Relay\Relay;
use FastRoute\Dispatcher;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Meritum\Http\Middleware\MiddlewareResolver;
use Meritum\Http\Exception\NotFoundHttpException;
use Meritum\Http\Middleware\RequestHandlerMiddleware;
use Meritum\Http\Exception\MethodNotAllowedHttpException;

final class Router implements RequestHandlerInterface
{
    /**
     * @param iterable<int, MiddlewareInterface|string> $middleware
     */
    public function __construct(
        private readonly iterable $middleware,
        private readonly Dispatcher $dispatcher,
        private readonly ContainerInterface $container
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $path   = $request->getUri()->getPath();

        $result = $this->dispatcher->dispatch($method, $path);

        if (Dispatcher::METHOD_NOT_ALLOWED === $result[0]) {
            /** @var string[] $allowedMethods */
            $allowedMethods = $result[1];

            $this->handleMethodNotAllowed($request, $method, $path, $allowedMethods);
        }

        if (Dispatcher::FOUND !== $result[0]) {
            $this->handleNotFound($request, $method, $path);
        }

        /** @var RouteInterface $route */
        $route = $result[1];

        /** @var array<string, string> $arguments */
        $arguments = $result[2];

        return $this->handleFound($route, $arguments, $request);
    }

    /**
     * @param string[] $allowedMethods
     *
     * @throws \Meritum\Http\Exception\MethodNotAllowedHttpException
     */
    private function handleMethodNotAllowed(ServerRequestInterface $request, string $method, string $uri, array $allowedMethods): never
    {
        throw new MethodNotAllowedHttpException($request, sprintf(
            'Method not allowed for route %s %s',
            $method,
            $uri
        ), $allowedMethods);
    }

    private function handleNotFound(ServerRequestInterface $request, string $method, string $uri): never
    {
        throw new NotFoundHttpException($request, sprintf(
            'Route not found %s %s',
            $method,
            $uri
        ));
    }

    /**
     * @param array<string, string> $arguments
     */
    private function handleFound(RouteInterface $route, array $arguments, ServerRequestInterface $request): ResponseInterface
    {
        $handler = $route->getHandler();

        $handler = is_string($handler) ? $this->container->get($handler) : $handler;

        if (!$handler instanceof RequestHandlerInterface) {
            throw new \RuntimeException(sprintf(
                'Invalid route handler [%s], route handler must implement %s',
                get_debug_type($handler),
                RequestHandlerInterface::class
            ));
        }

        $route = $route->withArguments($arguments);

        $request = $request->withAttribute('__route__', $route);

        $stack = [...$this->middleware, ...$route->getMiddleware(), new RequestHandlerMiddleware($handler)];

        return new Relay($stack, new MiddlewareResolver($this->container))->handle($request);
    }
}
