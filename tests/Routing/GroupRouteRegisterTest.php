<?php

namespace Meritum\Http\Test\Routing;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Meritum\Http\Routing\RouteCollection;
use Meritum\Http\Routing\GroupRouteRegister;
use Meritum\Http\Routing\RouteGroupInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GroupRouteRegisterTest extends TestCase
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

    public function test_add_route_prefixes_the_path(): void
    {
        $register = new GroupRouteRegister(new RouteCollection(), '/api');

        $route = $register->addRoute(['GET'], '/users', $this->handler);

        $this->assertSame('/api/users', $route->getPath());
    }

    public function test_add_route_normalizes_missing_leading_slash(): void
    {
        $register = new GroupRouteRegister(new RouteCollection(), '/api');

        $route = $register->addRoute(['GET'], 'users', $this->handler);

        $this->assertSame('/api/users', $route->getPath());
    }

    public function test_add_route_accepts_string_method(): void
    {
        $register = new GroupRouteRegister(new RouteCollection(), '/api');

        $route = $register->addRoute('GET', '/users', $this->handler);

        $this->assertSame(['GET'], $route->getMethods());
    }

    public function test_add_route_registers_the_route_on_the_collection(): void
    {
        $collection = new RouteCollection();
        $register   = new GroupRouteRegister($collection, '/api');

        $route = $register->addRoute(['GET'], '/users', $this->handler);

        $this->assertSame([$route], array_values(iterator_to_array($collection)));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function httpVerbMethodProvider(): array
    {
        return [
            'get'     => ['get', 'GET'],
            'post'    => ['post', 'POST'],
            'put'     => ['put', 'PUT'],
            'patch'   => ['patch', 'PATCH'],
            'delete'  => ['delete', 'DELETE'],
            'options' => ['options', 'OPTIONS'],
            'head'    => ['head', 'HEAD'],
        ];
    }

    #[DataProvider('httpVerbMethodProvider')]
    public function test_verb_method_registers_route_with_matching_method(string $verbMethod, string $expectedHttpMethod): void
    {
        $register = new GroupRouteRegister(new RouteCollection(), '/api');

        $route = $register->{$verbMethod}('/users', $this->handler);

        $this->assertSame([$expectedHttpMethod], $route->getMethods());
        $this->assertSame('/api/users', $route->getPath());
    }

    public function test_group_returns_route_group_interface(): void
    {
        $register = new GroupRouteRegister(new RouteCollection(), '/api');

        $group = $register->group('/v1', function () {});

        $this->assertInstanceOf(RouteGroupInterface::class, $group);
    }

    public function test_group_composes_nested_prefix(): void
    {
        $register = new GroupRouteRegister(new RouteCollection(), '/api');
        $route    = null;

        $register->group('/v1', function ($v1) use (&$route) {
            $route = $v1->get('/users', $this->handler);
        });

        $this->assertSame('/api/v1/users', $route->getPath());
    }

    public function test_group_normalizes_missing_leading_slash_on_child_prefix(): void
    {
        $register = new GroupRouteRegister(new RouteCollection(), '/api');
        $route    = null;

        $register->group('v1', function ($v1) use (&$route) {
            $route = $v1->get('/users', $this->handler);
        });

        $this->assertSame('/api/v1/users', $route->getPath());
    }
}
