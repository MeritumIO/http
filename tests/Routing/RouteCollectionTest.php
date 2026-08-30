<?php

namespace Meritum\Http\Test\Routing;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Routing\RouteCollection;
use Meritum\Http\Routing\RouteInterface;
use Meritum\Http\Routing\RouteGroupInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Georgeff\Kernel\Contract\DebuggableInterface;

final class RouteCollectionTest extends TestCase
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

    public function test_implements_iterator_aggregate(): void
    {
        $this->assertInstanceOf(\IteratorAggregate::class, new RouteCollection());
    }

    public function test_is_empty_by_default(): void
    {
        $this->assertTrue((new RouteCollection())->isEmpty());
    }

    public function test_is_not_empty_after_add(): void
    {
        $collection = new RouteCollection();
        $collection->add(['GET'], '/path', $this->handler);

        $this->assertFalse($collection->isEmpty());
    }

    public function test_add_returns_route_interface(): void
    {
        $collection = new RouteCollection();
        $route      = $collection->add(['GET'], '/path', $this->handler);

        $this->assertInstanceOf(RouteInterface::class, $route);
    }

    public function test_add_returns_the_created_route(): void
    {
        $collection = new RouteCollection();
        $route      = $collection->add(['GET'], '/path', $this->handler);

        $this->assertSame('/path', $route->getPath());
        $this->assertSame(['GET'], $route->getMethods());
    }

    public function test_iterator_yields_added_routes(): void
    {
        $collection = new RouteCollection();
        $route      = $collection->add(['GET'], '/path', $this->handler);

        $routes = iterator_to_array($collection);

        $this->assertCount(1, $routes);
        $this->assertSame($route, $routes[0]);
    }

    public function test_iterator_yields_routes_in_insertion_order(): void
    {
        $collection = new RouteCollection();
        $first      = $collection->add(['GET'], '/first', $this->handler);
        $second     = $collection->add(['POST'], '/second', $this->handler);

        $routes = iterator_to_array($collection);

        $this->assertSame($first, $routes[0]);
        $this->assertSame($second, $routes[1]);
    }

    public function test_get_iterator_returns_fresh_iterator_each_time(): void
    {
        $collection = new RouteCollection();
        $collection->add(['GET'], '/path', $this->handler);

        $iteratorA = $collection->getIterator();
        $iteratorB = $collection->getIterator();

        $this->assertNotSame($iteratorA, $iteratorB);
    }

    public function test_multiple_routes_can_be_added(): void
    {
        $collection = new RouteCollection();
        $collection->add(['GET'], '/a', $this->handler);
        $collection->add(['POST'], '/b', $this->handler);
        $collection->add(['PUT'], '/c', $this->handler);

        $this->assertCount(3, iterator_to_array($collection));
    }

    public function test_implements_debuggable_interface(): void
    {
        $this->assertInstanceOf(DebuggableInterface::class, new RouteCollection());
    }

    public function test_get_debug_info_is_empty_by_default(): void
    {
        $this->assertSame([], (new RouteCollection())->getDebugInfo());
    }

    public function test_get_debug_info_contains_each_routes_debug_info(): void
    {
        $collection = new RouteCollection();
        $route      = $collection->add(['GET'], '/path', $this->handler);

        $this->assertSame([$route->getDebugInfo()], $collection->getDebugInfo());
    }

    public function test_get_debug_info_preserves_insertion_order(): void
    {
        $collection = new RouteCollection();
        $first      = $collection->add(['GET'], '/first', $this->handler);
        $second     = $collection->add(['POST'], '/second', $this->handler);

        $this->assertSame([$first->getDebugInfo(), $second->getDebugInfo()], $collection->getDebugInfo());
    }

    private function middleware(): MiddlewareInterface
    {
        return new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
    }

    public function test_group_returns_route_group_interface(): void
    {
        $collection = new RouteCollection();

        $group = $collection->group('/api', function () {});

        $this->assertInstanceOf(RouteGroupInterface::class, $group);
    }

    public function test_group_invokes_the_callback(): void
    {
        $collection = new RouteCollection();
        $invoked    = false;

        $collection->group('/api', function () use (&$invoked) {
            $invoked = true;
        });

        $this->assertTrue($invoked);
    }

    public function test_group_prefixes_routes_registered_in_callback(): void
    {
        $collection = new RouteCollection();

        $collection->group('/api', function ($api) {
            $api->get('/users', $this->handler);
        });

        $routes = iterator_to_array($collection);

        $this->assertSame('/api/users', $routes[0]->getPath());
    }

    public function test_nested_groups_compose_prefixes(): void
    {
        $collection = new RouteCollection();

        $collection->group('/api', function ($api) {
            $api->group('/v1', function ($v1) {
                $v1->get('/users', $this->handler);
            });
        });

        $routes = iterator_to_array($collection);

        $this->assertSame('/api/v1/users', $routes[0]->getPath());
    }

    public function test_route_in_group_inherits_group_middleware(): void
    {
        $middleware = $this->middleware();
        $collection = new RouteCollection();

        $group = $collection->group('/api', function ($api) {
            $api->get('/users', $this->handler);
        });
        $group->addMiddleware($middleware);

        $routes = iterator_to_array($collection);

        $this->assertSame([$middleware], iterator_to_array($routes[0]->getMiddleware()));
    }

    public function test_route_outside_any_group_has_no_group_middleware(): void
    {
        $middleware = $this->middleware();
        $collection = new RouteCollection();

        $group = $collection->group('/api', function ($api) {
            $api->get('/users', $this->handler);
        });
        $group->addMiddleware($middleware);

        $collection->add(['GET'], '/outside', $this->handler);

        $routes = iterator_to_array($collection);

        $this->assertSame([], iterator_to_array($routes[1]->getMiddleware()));
    }

    public function test_group_middleware_order_is_outer_then_inner_then_route(): void
    {
        $outerMiddleware = $this->middleware();
        $innerMiddleware = $this->middleware();
        $routeMiddleware = $this->middleware();

        $collection = new RouteCollection();
        $route      = null;
        $innerGroup = null;

        $outerGroup = $collection->group('/api', function ($api) use (&$route, &$innerGroup, $routeMiddleware) {
            $innerGroup = $api->group('/v1', function ($v1) use (&$route, $routeMiddleware) {
                $route = $v1->get('/users', $this->handler);
                $route->addMiddleware($routeMiddleware);
            });
        });

        $outerGroup->addMiddleware($outerMiddleware);
        $innerGroup->addMiddleware($innerMiddleware);

        $this->assertSame(
            [$outerMiddleware, $innerMiddleware, $routeMiddleware],
            iterator_to_array($route->getMiddleware())
        );
    }

    public function test_get_middleware_on_grouped_route_is_idempotent(): void
    {
        $middleware = $this->middleware();
        $collection = new RouteCollection();
        $route      = null;

        $group = $collection->group('/api', function ($api) use (&$route) {
            $route = $api->get('/users', $this->handler);
        });
        $group->addMiddleware($middleware);

        $first  = iterator_to_array($route->getMiddleware());
        $second = iterator_to_array($route->getMiddleware());

        $this->assertSame([$middleware], $first);
        $this->assertSame([$middleware], $second);
    }

    public function test_exception_in_group_callback_does_not_leak_active_group_state(): void
    {
        $collection = new RouteCollection();

        try {
            $collection->group('/api', function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            // expected
        }

        $activeGroups = new \ReflectionProperty(RouteCollection::class, 'activeGroups');

        $this->assertSame([], $activeGroups->getValue($collection));
    }
}
