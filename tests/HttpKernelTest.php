<?php

namespace Meritum\Http\Test;

use Throwable;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Georgeff\Kernel\Exception\KernelException;
use Georgeff\Kernel\Environment\Testing as TestingEnvironment;
use Meritum\Http\Contract\EmitterInterface;
use Meritum\Http\Contract\ExceptionHandlerInterface;
use Meritum\Http\HttpKernel;
use Meritum\Http\HttpKernelInterface;
use Meritum\Http\Routing\RouteInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpKernelTest extends TestCase
{
    private function createKernel(): HttpKernel
    {
        return new HttpKernel(new TestingEnvironment());
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

    private function createRunnableKernel(string $path = '/test', string $method = 'GET'): HttpKernel
    {
        $kernel = $this->createKernel();
        $kernel->addRoute($method, $path, $this->createHandler());

        $kernel->override(ServerRequestInterface::class, fn() => new ServerRequest([], [], $path, $method))->share();
        $kernel->override(EmitterInterface::class, fn() => new class implements EmitterInterface {
            public function emit(ResponseInterface $response): void {}
        })->share();

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
    public function test_verb_method_returns_route_interface_for_matching_method(string $verbMethod, string $expectedHttpMethod): void
    {
        $route = $this->createKernel()->{$verbMethod}('/path', $this->createHandler());

        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertSame([$expectedHttpMethod], $route->getMethods());
    }

    #[DataProvider('httpVerbMethodProvider')]
    public function test_verb_method_dispatches_request_with_matching_method(string $verbMethod, string $expectedHttpMethod): void
    {
        $kernel = $this->createKernel();
        $kernel->{$verbMethod}('/path', $this->createHandler());
        $kernel->boot();

        $response = $kernel->handle(new ServerRequest([], [], '/path', $expectedHttpMethod));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_verb_method_throws_after_boot(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(KernelException::class);

        $kernel->get('/new', $this->createHandler());
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

    public function test_terminate_throws_when_request_has_not_been_handled(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(KernelException::class);

        $kernel->terminate(new ServerRequest(), new Response('php://temp', 200));
    }

    public function test_terminate_invokes_callbacks_in_order(): void
    {
        $kernel = $this->createKernel();
        $kernel->addRoute('GET', '/test', $this->createHandler());
        $order  = [];
        $kernel->onTerminating(function () use (&$order) { $order[] = 1; });
        $kernel->onTerminating(function () use (&$order) { $order[] = 2; });
        $kernel->boot();
        $kernel->handle(new ServerRequest([], [], '/test', 'GET'));

        $kernel->terminate(new ServerRequest(), new Response('php://temp', 200));

        $this->assertSame([1, 2], $order);
    }

    public function test_terminate_passes_request_response_and_kernel_to_callbacks(): void
    {
        $kernel = $this->createKernel();
        $kernel->addRoute('GET', '/test', $this->createHandler());
        $captured = [];
        $kernel->onTerminating(function ($req, $resp, $k) use (&$captured) {
            $captured = [$req, $resp, $k];
        });
        $kernel->boot();
        $kernel->handle(new ServerRequest([], [], '/test', 'GET'));

        $request  = new ServerRequest();
        $response = new Response('php://temp', 200);
        $kernel->terminate($request, $response);

        $this->assertSame($request, $captured[0]);
        $this->assertSame($response, $captured[1]);
        $this->assertSame($kernel, $captured[2]);
    }

    public function test_terminate_does_not_shut_down_kernel(): void
    {
        $kernel = $this->createBootedKernel();
        $kernel->handle(new ServerRequest([], [], '/test', 'GET'));

        $kernel->terminate(new ServerRequest(), new Response('php://temp', 200));

        $this->assertFalse($kernel->isShutdown());
    }

    public function test_run_executes_the_full_request_lifecycle(): void
    {
        $kernel = $this->createRunnableKernel();

        $emitter = new class implements EmitterInterface {
            public ?ResponseInterface $response = null;

            public function emit(ResponseInterface $response): void
            {
                $this->response = $response;
            }
        };

        $kernel->override(EmitterInterface::class, fn() => $emitter)->share();

        $terminated = false;
        $kernel->onTerminating(function () use (&$terminated) { $terminated = true; });

        $exitCode = $kernel->run();

        $this->assertSame(0, $exitCode);
        $this->assertTrue($kernel->isBooted());
        $this->assertTrue($kernel->isShutdown());
        $this->assertTrue($terminated);
        $this->assertSame(200, $emitter->response?->getStatusCode());
    }

    public function test_run_boots_an_unbooted_kernel(): void
    {
        $kernel = $this->createRunnableKernel();

        $this->assertFalse($kernel->isBooted());

        $kernel->run();

        $this->assertTrue($kernel->isBooted());
    }

    public function test_run_does_not_throw_when_kernel_already_booted(): void
    {
        $kernel = $this->createRunnableKernel();
        $kernel->boot();

        $this->assertSame(0, $kernel->run());
    }

    public function test_run_throws_when_kernel_is_shutdown(): void
    {
        $kernel = $this->createRunnableKernel();
        $kernel->boot();
        $kernel->shutdown();

        $this->expectException(KernelException::class);

        $kernel->run();
    }

    public function test_handle_registers_its_own_debug_profile(): void
    {
        $kernel = new HttpKernel(new TestingEnvironment(), debug: true);
        $kernel->addRoute('GET', '/test', $this->createHandler());
        $kernel->boot();

        $kernel->handle(new ServerRequest([], [], '/test', 'GET'));

        $this->assertArrayHasKey('handle', $kernel->getDebugInfo()['profiles']);
    }

    public function test_terminate_registers_its_own_debug_profile_when_called_outside_run(): void
    {
        $kernel = new HttpKernel(new TestingEnvironment(), debug: true);
        $kernel->addRoute('GET', '/test', $this->createHandler());
        $kernel->boot();
        $kernel->handle(new ServerRequest([], [], '/test', 'GET'));

        $kernel->terminate(new ServerRequest(), new Response('php://temp', 200));

        $this->assertArrayHasKey('terminate', $kernel->getDebugInfo()['profiles']);
    }

    public function test_run_registers_separate_debug_profiles_for_each_lifecycle_stage(): void
    {
        $kernel = new HttpKernel(new TestingEnvironment(), debug: true);
        $kernel->addRoute('GET', '/test', $this->createHandler());
        $kernel->override(ServerRequestInterface::class, fn() => new ServerRequest([], [], '/test', 'GET'))->share();
        $kernel->override(EmitterInterface::class, fn() => new class implements EmitterInterface {
            public function emit(ResponseInterface $response): void {}
        })->share();

        $kernel->run();

        $profiles = $kernel->getDebugInfo()['profiles'];

        $this->assertArrayHasKey('boot', $profiles);
        $this->assertArrayHasKey('run', $profiles);
        $this->assertArrayHasKey('handle', $profiles);
        $this->assertArrayHasKey('terminate', $profiles);
        $this->assertArrayHasKey('shutdown', $profiles);
    }

    public function test_debug_info_includes_registered_routes(): void
    {
        $kernel = new HttpKernel(new TestingEnvironment(), debug: true);
        $kernel->addRoute('GET', '/test', $this->createHandler());
        $kernel->boot();

        $routes = $kernel->getDebugInfo()['components']['routes'];

        $this->assertCount(1, $routes);
        $this->assertSame('/test', $routes[0]['path']);
    }

    public function test_debug_info_includes_registered_middleware(): void
    {
        $kernel = new HttpKernel(new TestingEnvironment(), debug: true);
        $kernel->addMiddleware('SomeMiddleware');
        $kernel->boot();

        $this->assertSame(['SomeMiddleware'], $kernel->getDebugInfo()['components']['middleware']);
    }
}
