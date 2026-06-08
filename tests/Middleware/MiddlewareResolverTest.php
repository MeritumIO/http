<?php

namespace Meritum\Http\Test\Middleware;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Middleware\MiddlewareResolver;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareResolverTest extends TestCase
{
    private MiddlewareInterface $middleware;

    protected function setUp(): void
    {
        $this->middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
    }

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

    public function test_returns_middleware_interface_directly(): void
    {
        $resolver = new MiddlewareResolver($this->container());

        $this->assertSame($this->middleware, $resolver($this->middleware));
    }

    public function test_resolves_string_from_container(): void
    {
        $resolver = new MiddlewareResolver($this->container([
            'some.middleware' => $this->middleware,
        ]));

        $this->assertSame($this->middleware, $resolver('some.middleware'));
    }

    public function test_throws_when_resolved_entry_is_not_middleware(): void
    {
        $resolver = new MiddlewareResolver($this->container([
            'not.middleware' => new \stdClass(),
        ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not.middleware');

        $resolver('not.middleware');
    }
}
