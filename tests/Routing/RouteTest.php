<?php

namespace Meritum\Http\Test\Routing;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Routing\Route;
use Meritum\Http\Routing\RouteGroup;
use Meritum\Http\Routing\RouteInterface;
use Meritum\Http\Routing\RouteGroupInterface;
use Meritum\Http\Routing\RouteRegistrationInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Georgeff\Kernel\Contract\DebuggableInterface;

final class RouteTest extends TestCase
{
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        $this->handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('Should not be called');
            }
        };
    }

    private function registrar(): RouteRegistrationInterface
    {
        return new class implements RouteRegistrationInterface {
            public function group(string $prefix, callable $callback): RouteGroupInterface
            {
                throw new \LogicException('Should not be called');
            }

            public function addRoute(array|string $methods, string $uri, RequestHandlerInterface|string $handler): RouteInterface
            {
                throw new \LogicException('Should not be called');
            }

            public function get(string $uri, RequestHandlerInterface|string $handler): RouteInterface
            {
                throw new \LogicException('Should not be called');
            }

            public function post(string $uri, RequestHandlerInterface|string $handler): RouteInterface
            {
                throw new \LogicException('Should not be called');
            }

            public function put(string $uri, RequestHandlerInterface|string $handler): RouteInterface
            {
                throw new \LogicException('Should not be called');
            }

            public function patch(string $uri, RequestHandlerInterface|string $handler): RouteInterface
            {
                throw new \LogicException('Should not be called');
            }

            public function delete(string $uri, RequestHandlerInterface|string $handler): RouteInterface
            {
                throw new \LogicException('Should not be called');
            }

            public function options(string $uri, RequestHandlerInterface|string $handler): RouteInterface
            {
                throw new \LogicException('Should not be called');
            }

            public function head(string $uri, RequestHandlerInterface|string $handler): RouteInterface
            {
                throw new \LogicException('Should not be called');
            }
        };
    }

    private function createGroup(?MiddlewareInterface $middleware = null): RouteGroup
    {
        $group = new RouteGroup('/group', function () {}, $this->registrar());

        if (null !== $middleware) {
            $group->addMiddleware($middleware);
        }

        return $group;
    }

    public function test_methods_are_uppercased(): void
    {
        $route = new Route(['get', 'post'], '/path', $this->handler);

        $this->assertSame(['GET', 'POST'], $route->getMethods());
    }

    public function test_path_gets_leading_slash_prepended(): void
    {
        $route = new Route(['GET'], 'users', $this->handler);

        $this->assertSame('/users', $route->getPath());
    }

    public function test_path_with_leading_slash_is_unchanged(): void
    {
        $route = new Route(['GET'], '/users', $this->handler);

        $this->assertSame('/users', $route->getPath());
    }

    public function test_empty_path_gets_slash(): void
    {
        $route = new Route(['GET'], '', $this->handler);

        $this->assertSame('/', $route->getPath());
    }

    public function test_get_handler_returns_handler_instance(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertSame($this->handler, $route->getHandler());
    }

    public function test_get_handler_returns_string(): void
    {
        $route = new Route(['GET'], '/path', 'SomeHandler');

        $this->assertSame('SomeHandler', $route->getHandler());
    }

    public function test_with_arguments_returns_new_instance(): void
    {
        $route = new Route(['GET'], '/users/{id}', $this->handler);
        $clone = $route->withArguments(['id' => '42']);

        $this->assertNotSame($route, $clone);
    }

    public function test_with_arguments_does_not_mutate_original(): void
    {
        $route = new Route(['GET'], '/users/{id}', $this->handler);
        $route->withArguments(['id' => '42']);

        $this->assertSame([], $route->getArguments());
    }

    public function test_with_arguments_sets_arguments_on_clone(): void
    {
        $route    = new Route(['GET'], '/users/{id}', $this->handler);
        $clone    = $route->withArguments(['id' => '42']);

        $this->assertSame(['id' => '42'], $clone->getArguments());
    }

    public function test_get_argument_returns_value(): void
    {
        $route = (new Route(['GET'], '/users/{id}', $this->handler))->withArguments(['id' => '42']);

        $this->assertSame('42', $route->getArgument('id'));
    }

    public function test_get_argument_returns_null_default_when_missing(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertNull($route->getArgument('id'));
    }

    public function test_get_argument_returns_custom_default_when_missing(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertSame('default', $route->getArgument('id', 'default'));
    }

    public function test_has_middleware_is_false_by_default(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertFalse($route->hasMiddleware());
    }

    public function test_has_middleware_is_true_after_adding(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/path', $this->handler);
        $route->addMiddleware($middleware);

        $this->assertTrue($route->hasMiddleware());
    }

    public function test_add_middleware_returns_same_instance(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertSame($route, $route->addMiddleware($middleware));
    }

    public function test_get_middleware_is_iterable(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertIsIterable($route->getMiddleware());
    }

    public function test_get_middleware_contains_added_entries(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/path', $this->handler);
        $route->addMiddleware($middleware);
        $route->addMiddleware('SomeMiddleware');

        $this->assertSame([$middleware, 'SomeMiddleware'], iterator_to_array($route->getMiddleware()));
    }

    public function test_with_arguments_clones_middleware_stack(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/users/{id}', $this->handler);
        $clone = $route->withArguments(['id' => '42']);

        $clone->addMiddleware($middleware);

        $this->assertFalse($route->hasMiddleware());
        $this->assertTrue($clone->hasMiddleware());
    }

    public function test_get_id_returns_a_non_empty_string(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertIsString($route->getId());
        $this->assertNotSame('', $route->getId());
    }

    public function test_get_id_is_the_same_for_routes_with_the_same_methods_and_path(): void
    {
        $first  = new Route(['GET'], '/path', $this->handler);
        $second = new Route(['GET'], '/path', $this->handler);

        $this->assertSame($first->getId(), $second->getId());
    }

    public function test_get_id_differs_for_different_paths(): void
    {
        $first  = new Route(['GET'], '/path', $this->handler);
        $second = new Route(['GET'], '/other', $this->handler);

        $this->assertNotSame($first->getId(), $second->getId());
    }

    public function test_get_id_differs_for_different_methods(): void
    {
        $first  = new Route(['GET'], '/path', $this->handler);
        $second = new Route(['POST'], '/path', $this->handler);

        $this->assertNotSame($first->getId(), $second->getId());
    }

    public function test_get_id_is_unchanged_by_method_order(): void
    {
        $first  = new Route(['GET', 'POST'], '/path', $this->handler);
        $second = new Route(['POST', 'GET'], '/path', $this->handler);

        $this->assertSame($first->getId(), $second->getId());
    }

    public function test_get_id_is_unchanged_by_method_casing(): void
    {
        $first  = new Route(['get'], '/path', $this->handler);
        $second = new Route(['GET'], '/path', $this->handler);

        $this->assertSame($first->getId(), $second->getId());
    }

    public function test_get_debug_info_contains_id(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertSame($route->getId(), $route->getDebugInfo()['id']);
    }

    public function test_implements_route_interface(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertInstanceOf(RouteInterface::class, $route);
    }

    public function test_implements_debuggable_interface(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertInstanceOf(DebuggableInterface::class, $route);
    }

    public function test_get_debug_info_contains_methods_path_and_handler(): void
    {
        $route = new Route(['GET', 'POST'], '/path', $this->handler);

        $debugInfo = $route->getDebugInfo();

        $this->assertSame(['GET', 'POST'], $debugInfo['methods']);
        $this->assertSame('/path', $debugInfo['path']);
        $this->assertSame($this->handler::class, $debugInfo['handler']);
    }

    public function test_get_debug_info_returns_string_handler_unchanged(): void
    {
        $route = new Route(['GET'], '/path', 'SomeHandler');

        $this->assertSame('SomeHandler', $route->getDebugInfo()['handler']);
    }

    public function test_get_debug_info_contains_arguments(): void
    {
        $route = (new Route(['GET'], '/users/{id}', $this->handler))->withArguments(['id' => '42']);

        $this->assertSame(['id' => '42'], $route->getDebugInfo()['arguments']);
    }

    public function test_get_debug_info_contains_middleware_debug_info(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/path', $this->handler);
        $route->addMiddleware($middleware);

        $this->assertSame([$middleware::class], $route->getDebugInfo()['middleware']);
    }

    public function test_has_middleware_is_true_when_only_group_has_middleware(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/path', $this->handler, [$this->createGroup($middleware)]);

        $this->assertTrue($route->hasMiddleware());
    }

    public function test_has_middleware_is_false_when_neither_route_nor_groups_have_middleware(): void
    {
        $route = new Route(['GET'], '/path', $this->handler, [$this->createGroup()]);

        $this->assertFalse($route->hasMiddleware());
    }

    public function test_get_middleware_includes_group_middleware(): void
    {
        $groupMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/path', $this->handler, [$this->createGroup($groupMiddleware)]);

        $this->assertSame([$groupMiddleware], iterator_to_array($route->getMiddleware()));
    }

    public function test_get_middleware_places_group_middleware_before_route_middleware(): void
    {
        $groupMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $routeMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/path', $this->handler, [$this->createGroup($groupMiddleware)]);
        $route->addMiddleware($routeMiddleware);

        $this->assertSame([$groupMiddleware, $routeMiddleware], iterator_to_array($route->getMiddleware()));
    }

    public function test_get_middleware_places_outer_group_middleware_before_inner_group_middleware(): void
    {
        $outerMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $innerMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        // RouteCollection::$activeGroups accumulates outermost-first, so that's the order Route receives them in
        $route = new Route(['GET'], '/path', $this->handler, [
            $this->createGroup($outerMiddleware),
            $this->createGroup($innerMiddleware),
        ]);

        $this->assertSame([$outerMiddleware, $innerMiddleware], iterator_to_array($route->getMiddleware()));
    }

    public function test_get_middleware_is_idempotent(): void
    {
        $groupMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/path', $this->handler, [$this->createGroup($groupMiddleware)]);

        $first  = iterator_to_array($route->getMiddleware());
        $second = iterator_to_array($route->getMiddleware());

        $this->assertSame([$groupMiddleware], $first);
        $this->assertSame([$groupMiddleware], $second);
    }

    public function test_get_debug_info_contains_empty_groups_by_default(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertSame([], $route->getDebugInfo()['groups']);
    }

    public function test_get_debug_info_contains_group_debug_info(): void
    {
        $group = $this->createGroup();

        $route = new Route(['GET'], '/path', $this->handler, [$group]);

        $this->assertSame([$group->getDebugInfo()], $route->getDebugInfo()['groups']);
    }

    public function test_get_debug_info_preserves_group_order(): void
    {
        $outerGroup = $this->createGroup();
        $innerGroup = $this->createGroup();

        $route = new Route(['GET'], '/path', $this->handler, [$outerGroup, $innerGroup]);

        $this->assertSame(
            [$outerGroup->getDebugInfo(), $innerGroup->getDebugInfo()],
            $route->getDebugInfo()['groups']
        );
    }

    public function test_get_debug_info_middleware_includes_group_middleware(): void
    {
        $groupMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $routeMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/path', $this->handler, [$this->createGroup($groupMiddleware)]);
        $route->addMiddleware($routeMiddleware);

        $this->assertSame(
            [$groupMiddleware::class, $routeMiddleware::class],
            $route->getDebugInfo()['middleware']
        );
    }
}
