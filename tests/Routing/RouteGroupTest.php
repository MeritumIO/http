<?php

namespace Meritum\Http\Test\Routing;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Routing\RouteGroup;
use Meritum\Http\Routing\RouteInterface;
use Meritum\Http\Routing\RouteGroupInterface;
use Meritum\Http\Routing\RouteRegistrationInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouteGroupTest extends TestCase
{
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

    public function test_implements_route_group_interface(): void
    {
        $group = new RouteGroup('/api', function () {}, $this->registrar());

        $this->assertInstanceOf(RouteGroupInterface::class, $group);
    }

    public function test_get_prefix_returns_constructor_value(): void
    {
        $group = new RouteGroup('/api', function () {}, $this->registrar());

        $this->assertSame('/api', $group->getPrefix());
    }

    public function test_add_middleware_returns_same_instance(): void
    {
        $group = new RouteGroup('/api', function () {}, $this->registrar());

        $this->assertSame($group, $group->addMiddleware('SomeMiddleware'));
    }

    public function test_get_middleware_stack_reflects_added_middleware(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $group = new RouteGroup('/api', function () {}, $this->registrar());
        $group->addMiddleware($middleware);

        $this->assertSame([$middleware], iterator_to_array($group->getMiddlewareStack()));
    }

    public function test_get_middleware_stack_is_empty_by_default(): void
    {
        $group = new RouteGroup('/api', function () {}, $this->registrar());

        $this->assertTrue($group->getMiddlewareStack()->isEmpty());
    }

    public function test_invoke_callback_calls_the_callback_with_the_registrar(): void
    {
        $registrar = $this->registrar();
        $received  = null;

        $group = new RouteGroup('/api', function ($r) use (&$received) {
            $received = $r;
        }, $registrar);

        $group->invokeCallback();

        $this->assertSame($registrar, $received);
    }

    public function test_invoke_callback_returns_same_instance(): void
    {
        $group = new RouteGroup('/api', function () {}, $this->registrar());

        $this->assertSame($group, $group->invokeCallback());
    }
}
