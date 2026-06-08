<?php

namespace Meritum\Http\Test\Routing;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Routing\RouteCollection;
use Meritum\Http\Routing\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
}
