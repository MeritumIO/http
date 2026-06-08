<?php

namespace Meritum\Http\Test\Routing;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Routing\Route;
use Meritum\Http\Routing\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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

    public function test_implements_route_interface(): void
    {
        $route = new Route(['GET'], '/path', $this->handler);

        $this->assertInstanceOf(RouteInterface::class, $route);
    }
}
