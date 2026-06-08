<?php

namespace Meritum\Http\Test\Routing;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Routing\Route;
use Meritum\Http\Routing\RouteCollection;
use Meritum\Http\Routing\RouterFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterFactoryTest extends TestCase
{
    private function container(array $bindings = []): ContainerInterface
    {
        return new class($bindings) implements ContainerInterface {
            public function __construct(private array $bindings) {}

            public function get(string $id): mixed
            {
                if (!$this->has($id)) {
                    throw new \RuntimeException("Not found: {$id}");
                }

                return $this->bindings[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->bindings);
            }
        };
    }

    public function test_produces_request_handler(): void
    {
        $factory = new RouterFactory([], new RouteCollection());
        $router  = $factory($this->container());

        $this->assertInstanceOf(RequestHandlerInterface::class, $router);
    }

    public function test_routes_are_dispatched_by_produced_router(): void
    {
        $response = new Response();
        $handler  = new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $collection = new RouteCollection();
        $collection->add(['GET'], '/users', $handler);

        $factory = new RouterFactory([], $collection);
        $router  = $factory($this->container());
        $request = new ServerRequest([], [], '/users', 'GET');

        $this->assertSame($response, $router->handle($request));
    }

    public function test_global_middleware_is_passed_to_router(): void
    {
        $executed = false;
        $response = new Response();

        $middleware = new class($executed) implements \Psr\Http\Server\MiddlewareInterface {
            public function __construct(private bool &$executed) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->executed = true;

                return $handler->handle($request);
            }
        };

        $handler = new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $collection = new RouteCollection();
        $collection->add(['GET'], '/path', $handler);

        $factory = new RouterFactory([$middleware], $collection);
        $router  = $factory($this->container());
        $request = new ServerRequest([], [], '/path', 'GET');

        $router->handle($request);

        $this->assertTrue($executed);
    }
}
