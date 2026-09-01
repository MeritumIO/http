<?php

namespace Meritum\Http\Test\Routing;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Routing\Route;
use Meritum\Http\Routing\RouteCollection;
use Meritum\Http\Routing\RouterFactory;
use Meritum\Http\Exception\NotFoundHttpException;
use Meritum\Http\Exception\RouteCacheException;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterFactoryTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $cacheFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->cacheFiles as $cacheFile) {
            if (is_file($cacheFile)) {
                unlink($cacheFile);
            }
        }
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

    private function cacheFile(): string
    {
        $file = sys_get_temp_dir() . '/meritum-http-route-cache-' . bin2hex(random_bytes(8)) . '.php';

        $this->cacheFiles[] = $file;

        return $file;
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    public function test_produces_request_handler(): void
    {
        $factory = new RouterFactory([], new RouteCollection(), fn(): ?string => null);
        $router  = $factory($this->container());

        $this->assertInstanceOf(RequestHandlerInterface::class, $router);
    }

    public function test_routes_are_dispatched_by_produced_router(): void
    {
        $response = new Response();
        $handler  = $this->handler($response);

        $collection = new RouteCollection();
        $collection->add(['GET'], '/users', $handler);

        $factory = new RouterFactory([], $collection, fn(): ?string => null);
        $router  = $factory($this->container());
        $request = new ServerRequest([], [], '/users', 'GET');

        $this->assertSame($response, $router->handle($request));
    }

    public function test_global_middleware_is_passed_to_router(): void
    {
        $executed = false;
        $response = new Response();

        $middleware = new class($executed) implements \Psr\Http\Server\MiddlewareInterface {
            public function __construct(private bool &$executed) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->executed = true;

                return $handler->handle($request);
            }
        };

        $handler = $this->handler($response);

        $collection = new RouteCollection();
        $collection->add(['GET'], '/path', $handler);

        $factory = new RouterFactory([$middleware], $collection, fn(): ?string => null);
        $router  = $factory($this->container());
        $request = new ServerRequest([], [], '/path', 'GET');

        $router->handle($request);

        $this->assertTrue($executed);
    }

    public function test_no_cache_file_is_created_when_caching_is_disabled(): void
    {
        $cacheFile = $this->cacheFile();

        $factory = new RouterFactory([], new RouteCollection(), fn(): ?string => null);
        $factory($this->container());

        $this->assertFileDoesNotExist($cacheFile);
    }

    public function test_a_cache_file_is_written_when_caching_is_enabled(): void
    {
        $cacheFile = $this->cacheFile();

        $collection = new RouteCollection();
        $collection->add(['GET'], '/users', $this->handler(new Response()));

        $factory = new RouterFactory([], $collection, fn(): ?string => $cacheFile);
        $factory($this->container());

        $this->assertFileExists($cacheFile);
    }

    public function test_routes_are_dispatched_correctly_on_the_first_call_that_writes_the_cache(): void
    {
        $cacheFile = $this->cacheFile();
        $response  = new Response();

        $collection = new RouteCollection();
        $collection->add(['GET'], '/users', $this->handler($response));

        $factory = new RouterFactory([], $collection, fn(): ?string => $cacheFile);
        $router  = $factory($this->container());
        $request = new ServerRequest([], [], '/users', 'GET');

        $this->assertSame($response, $router->handle($request));
    }

    public function test_a_second_factory_reuses_an_existing_cache_file_instead_of_rescanning_routes(): void
    {
        $cacheFile = $this->cacheFile();
        $response  = new Response();

        $collection = new RouteCollection();
        $collection->add(['GET'], '/users', $this->handler($response));

        $first = new RouterFactory([], $collection, fn(): ?string => $cacheFile);
        $first($this->container());

        $this->assertFileExists($cacheFile);

        // A route added after the cache file was written; the stale cache should not know about it.
        $collection->add(['GET'], '/posts', $this->handler(new Response()));

        $second = new RouterFactory([], $collection, fn(): ?string => $cacheFile);
        $router = $second($this->container());

        // The originally cached route still dispatches correctly...
        $this->assertSame($response, $router->handle(new ServerRequest([], [], '/users', 'GET')));

        // ...but the route added after caching is invisible to the stale cache file.
        $this->expectException(NotFoundHttpException::class);

        $router->handle(new ServerRequest([], [], '/posts', 'GET'));
    }

    public function test_invoking_throws_a_route_cache_exception_when_the_cache_file_is_corrupt(): void
    {
        $cacheFile = $this->cacheFile();
        file_put_contents($cacheFile, '<?php return null;');

        $factory = new RouterFactory([], new RouteCollection(), fn(): ?string => $cacheFile);

        $this->expectException(RouteCacheException::class);

        $factory($this->container());
    }

    public function test_route_cache_exception_preserves_the_original_exception_as_previous(): void
    {
        $cacheFile = $this->cacheFile();
        file_put_contents($cacheFile, '<?php return null;');

        $factory = new RouterFactory([], new RouteCollection(), fn(): ?string => $cacheFile);

        try {
            $factory($this->container());
            $this->fail('Expected RouteCacheException');
        } catch (RouteCacheException $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertSame('Invalid cache file "' . $cacheFile . '"', $e->getPrevious()?->getMessage());
        }
    }
}
