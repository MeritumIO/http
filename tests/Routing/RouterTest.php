<?php

namespace Meritum\Http\Test\Routing;

use FastRoute\Dispatcher;
use PHPUnit\Framework\TestCase;
use Meritum\Http\Routing\Route;
use Meritum\Http\Routing\Router;
use Meritum\Http\Routing\RouteInterface;
use Meritum\Http\Exception\RoutingException;
use Meritum\Http\Exception\NotFoundHttpException;
use Meritum\Http\Exception\MethodNotAllowedHttpException;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterTest extends TestCase
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

    private function dispatcher(array $routes): Dispatcher
    {
        return \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r) use ($routes) {
            foreach ($routes as [$methods, $path, $route]) {
                $r->addRoute($methods, $path, $route);
            }
        });
    }

    private function response(): ResponseInterface
    {
        return new Response();
    }

    public function test_implements_request_handler_interface(): void
    {
        $router = new Router([], $this->dispatcher([]), $this->container());

        $this->assertInstanceOf(RequestHandlerInterface::class, $router);
    }

    public function test_dispatches_to_matched_route(): void
    {
        $response = $this->response();
        $handler  = new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $route      = new Route(['GET'], '/users', $handler);
        $dispatcher = $this->dispatcher([[['GET'], '/users', $route]]);
        $router     = new Router([], $dispatcher, $this->container());
        $request    = new ServerRequest([], [], '/users', 'GET');

        $this->assertSame($response, $router->handle($request));
    }

    public function test_throws_not_found_for_unmatched_route(): void
    {
        $router  = new Router([], $this->dispatcher([]), $this->container());
        $request = new ServerRequest([], [], '/missing', 'GET');

        $this->expectException(NotFoundHttpException::class);

        $router->handle($request);
    }

    public function test_throws_method_not_allowed_for_wrong_method(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('Should not be called');
            }
        };

        $route      = new Route(['GET'], '/users', $handler);
        $dispatcher = $this->dispatcher([[['GET'], '/users', $route]]);
        $router     = new Router([], $dispatcher, $this->container());
        $request    = new ServerRequest([], [], '/users', 'POST');

        $this->expectException(MethodNotAllowedHttpException::class);

        $router->handle($request);
    }

    public function test_method_not_allowed_exception_contains_allowed_methods(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('Should not be called');
            }
        };

        $route      = new Route(['GET', 'PUT'], '/users', $handler);
        $dispatcher = $this->dispatcher([[['GET', 'PUT'], '/users', $route]]);
        $router     = new Router([], $dispatcher, $this->container());
        $request    = new ServerRequest([], [], '/users', 'POST');

        try {
            $router->handle($request);
            $this->fail('Expected MethodNotAllowedHttpException');
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertContains('GET', $e->allowedMethods);
            $this->assertContains('PUT', $e->allowedMethods);
        }
    }

    public function test_route_arguments_are_bound_to_request(): void
    {
        $capturedRoute = null;
        $handler       = new class($capturedRoute) implements RequestHandlerInterface {
            public function __construct(private mixed &$capturedRoute) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capturedRoute = $request->getAttribute(RouteInterface::class);

                return new Response();
            }
        };

        $route      = new Route(['GET'], '/users/{id}', $handler);
        $dispatcher = $this->dispatcher([[['GET'], '/users/{id}', $route]]);
        $router     = new Router([], $dispatcher, $this->container());
        $request    = new ServerRequest([], [], '/users/42', 'GET');

        $router->handle($request);

        $this->assertSame('42', $capturedRoute->getArgument('id'));
    }

    public function test_resolves_string_handler_from_container(): void
    {
        $response = $this->response();
        $handler  = new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $route      = new Route(['GET'], '/path', 'handler.service');
        $dispatcher = $this->dispatcher([[['GET'], '/path', $route]]);
        $router     = new Router([], $dispatcher, $this->container(['handler.service' => $handler]));
        $request    = new ServerRequest([], [], '/path', 'GET');

        $this->assertSame($response, $router->handle($request));
    }

    public function test_throws_routing_exception_for_invalid_handler(): void
    {
        $route      = new Route(['GET'], '/path', 'bad.handler');
        $dispatcher = $this->dispatcher([[['GET'], '/path', $route]]);
        $router     = new Router([], $dispatcher, $this->container(['bad.handler' => new \stdClass()]));
        $request    = new ServerRequest([], [], '/path', 'GET');

        $this->expectException(RoutingException::class);

        $router->handle($request);
    }

    public function test_throws_routing_exception_when_handler_service_not_found(): void
    {
        $route      = new Route(['GET'], '/path', 'missing.handler');
        $dispatcher = $this->dispatcher([[['GET'], '/path', $route]]);
        $router     = new Router([], $dispatcher, $this->container());
        $request    = new ServerRequest([], [], '/path', 'GET');

        $this->expectException(RoutingException::class);

        $router->handle($request);
    }

    public function test_routing_exception_preserves_the_container_exception_as_previous(): void
    {
        $route      = new Route(['GET'], '/path', 'missing.handler');
        $dispatcher = $this->dispatcher([[['GET'], '/path', $route]]);
        $router     = new Router([], $dispatcher, $this->container());
        $request    = new ServerRequest([], [], '/path', 'GET');

        try {
            $router->handle($request);
            $this->fail('Expected RoutingException');
        } catch (RoutingException $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertSame('Not found: missing.handler', $e->getPrevious()?->getMessage());
        }
    }

    public function test_global_middleware_is_executed(): void
    {
        $executed = false;
        $response = $this->response();

        $middleware = new class($executed) implements MiddlewareInterface {
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

        $route      = new Route(['GET'], '/path', $handler);
        $dispatcher = $this->dispatcher([[['GET'], '/path', $route]]);
        $router     = new Router([$middleware], $dispatcher, $this->container());
        $request    = new ServerRequest([], [], '/path', 'GET');

        $router->handle($request);

        $this->assertTrue($executed);
    }

    public function test_route_middleware_is_executed(): void
    {
        $executed = false;
        $response = $this->response();

        $routeMiddleware = new class($executed) implements MiddlewareInterface {
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

        $route = new Route(['GET'], '/path', $handler);
        $route->addMiddleware($routeMiddleware);

        $dispatcher = $this->dispatcher([[['GET'], '/path', $route]]);
        $router     = new Router([], $dispatcher, $this->container());
        $request    = new ServerRequest([], [], '/path', 'GET');

        $router->handle($request);

        $this->assertTrue($executed);
    }

    public function test_global_middleware_runs_before_route_middleware(): void
    {
        $order    = [];
        $response = $this->response();

        $globalMiddleware = new class($order) implements MiddlewareInterface {
            public function __construct(private array &$order) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'global';

                return $handler->handle($request);
            }
        };

        $routeMiddleware = new class($order) implements MiddlewareInterface {
            public function __construct(private array &$order) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'route';

                return $handler->handle($request);
            }
        };

        $handler = new class($order, $response) implements RequestHandlerInterface {
            public function __construct(private array &$order, private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->order[] = 'handler';

                return $this->response;
            }
        };

        $route = new Route(['GET'], '/path', $handler);
        $route->addMiddleware($routeMiddleware);

        $dispatcher = $this->dispatcher([[['GET'], '/path', $route]]);
        $router     = new Router([$globalMiddleware], $dispatcher, $this->container());
        $request    = new ServerRequest([], [], '/path', 'GET');

        $router->handle($request);

        $this->assertSame(['global', 'route', 'handler'], $order);
    }

    public function test_resolves_string_middleware_from_container(): void
    {
        $executed = false;
        $response = $this->response();

        $middleware = new class($executed) implements MiddlewareInterface {
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

        $route = new Route(['GET'], '/path', $handler);
        $route->addMiddleware('some.middleware');

        $dispatcher = $this->dispatcher([[['GET'], '/path', $route]]);
        $router     = new Router([], $dispatcher, $this->container(['some.middleware' => $middleware]));
        $request    = new ServerRequest([], [], '/path', 'GET');

        $router->handle($request);

        $this->assertTrue($executed);
    }
}
