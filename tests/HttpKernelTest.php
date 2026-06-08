<?php

namespace Meritum\Http\Test;

use Throwable;
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\KernelException;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Meritum\Http\Exception\ExceptionHandlerInterface;
use Meritum\Http\HttpKernel;
use Meritum\Http\HttpKernelInterface;
use Meritum\Http\Routing\RouteInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpKernelTest extends TestCase
{
    private function createKernel(): HttpKernel
    {
        return new HttpKernel(Environment::Testing);
    }

    private function createHandler(int $status = 200): RequestHandlerInterface
    {
        return new class($status) implements RequestHandlerInterface {
            public function __construct(private readonly int $status) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response('php://temp', $this->status);
            }
        };
    }

    private function createBootedKernel(string $path = '/test', string $method = 'GET'): HttpKernel
    {
        $kernel = $this->createKernel();
        $kernel->addRoute($method, $path, $this->createHandler());
        $kernel->boot();

        return $kernel;
    }

    public function test_implements_http_kernel_interface(): void
    {
        $this->assertInstanceOf(HttpKernelInterface::class, $this->createKernel());
    }

    public function test_handle_throws_when_not_booted(): void
    {
        $this->expectException(KernelException::class);

        $this->createKernel()->handle(new ServerRequest());
    }

    public function test_handle_throws_when_shutdown(): void
    {
        $kernel = $this->createBootedKernel();
        $kernel->shutdown();

        $this->expectException(KernelException::class);

        $kernel->handle(new ServerRequest());
    }

    public function test_add_route_returns_route_interface(): void
    {
        $route = $this->createKernel()->addRoute(['GET', 'POST'], '/test', $this->createHandler());

        $this->assertInstanceOf(RouteInterface::class, $route);
    }

    public function test_add_route_accepts_string_method(): void
    {
        $kernel = $this->createKernel();
        $kernel->addRoute('GET', '/test', $this->createHandler());
        $kernel->boot();

        $response = $kernel->handle(new ServerRequest([], [], '/test', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_add_route_throws_after_boot(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(KernelException::class);

        $kernel->addRoute('GET', '/new', $this->createHandler());
    }

    public function test_add_middleware_returns_static(): void
    {
        $kernel = $this->createKernel();

        $this->assertSame($kernel, $kernel->addMiddleware('SomeMiddleware'));
    }

    public function test_add_middleware_throws_after_boot(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(KernelException::class);

        $kernel->addMiddleware('SomeMiddleware');
    }

    public function test_on_terminating_returns_static(): void
    {
        $kernel = $this->createKernel();

        $this->assertSame($kernel, $kernel->onTerminating(function () {}));
    }

    public function test_on_terminating_throws_after_boot(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(KernelException::class);

        $kernel->onTerminating(function () {});
    }

    public function test_handle_dispatches_request_to_handler(): void
    {
        $response = $this->createBootedKernel()->handle(new ServerRequest([], [], '/test', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handle_invokes_exception_handler_when_throwable_occurs(): void
    {
        $kernel = $this->createKernel();
        $kernel->addRoute('GET', '/test', new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Handler error');
            }
        });
        $kernel->define(ExceptionHandlerInterface::class, fn() => new class implements ExceptionHandlerInterface {
            public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface
            {
                return new Response('php://temp', 500);
            }
        })->share();
        $kernel->boot();

        $response = $kernel->handle(new ServerRequest([], [], '/test', 'GET'));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function test_handle_rethrows_when_no_exception_handler_registered(): void
    {
        $kernel = $this->createKernel();
        $kernel->addRoute('GET', '/test', new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Handler error');
            }
        });
        $kernel->boot();

        $this->expectException(\RuntimeException::class);

        $kernel->handle(new ServerRequest([], [], '/test', 'GET'));
    }

    public function test_terminate_invokes_callbacks_in_order(): void
    {
        $kernel = $this->createKernel();
        $order  = [];
        $kernel->onTerminating(function () use (&$order) { $order[] = 1; });
        $kernel->onTerminating(function () use (&$order) { $order[] = 2; });
        $kernel->boot();

        $kernel->terminate(new ServerRequest(), new Response('php://temp', 200));

        $this->assertSame([1, 2], $order);
    }

    public function test_terminate_passes_request_response_and_kernel_to_callbacks(): void
    {
        $kernel   = $this->createKernel();
        $captured = [];
        $kernel->onTerminating(function ($req, $resp, $k) use (&$captured) {
            $captured = [$req, $resp, $k];
        });
        $kernel->boot();

        $request  = new ServerRequest();
        $response = new Response('php://temp', 200);
        $kernel->terminate($request, $response);

        $this->assertSame($request, $captured[0]);
        $this->assertSame($response, $captured[1]);
        $this->assertSame($kernel, $captured[2]);
    }

    public function test_default_terminate_callback_shuts_down_kernel(): void
    {
        $kernel = $this->createBootedKernel();

        $this->assertFalse($kernel->isShutdown());

        $kernel->terminate(new ServerRequest(), new Response('php://temp', 200));

        $this->assertTrue($kernel->isShutdown());
    }
}
