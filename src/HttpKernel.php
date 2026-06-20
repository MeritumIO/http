<?php

namespace Meritum\Http;

use Throwable;
use Georgeff\Kernel\Kernel;
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\Debug\Profiler;
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\KernelException;
use Georgeff\Kernel\ServiceRegistrar;
use Meritum\Http\Emitter\SapiEmitter;
use Meritum\Http\Routing\RouterFactory;
use Psr\Http\Message\ResponseInterface;
use Meritum\Http\Routing\RouteInterface;
use Psr\Http\Server\MiddlewareInterface;
use Meritum\Http\Routing\RouteCollection;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;
use Meritum\Http\Middleware\MiddlewareStack;
use Psr\Http\Server\RequestHandlerInterface;
use Meritum\Http\Exception\ExceptionHandlerInterface;

final class HttpKernel extends Kernel implements HttpKernelInterface
{
    private ?Profiler $requestProfile = null;

    private readonly RouteCollection $routes;

    private readonly MiddlewareStack $middleware;

    /**
     * @var array<int, callable(ServerRequestInterface, ResponseInterface, KernelInterface): void>
     */
    private array $terminatingCallbacks = [];

    public function __construct(Environment $environment, ?ServiceRegistrar $registrar = null, bool $debug = false)
    {
        parent::__construct($environment, $registrar, $debug);

        $this->routes     = new RouteCollection();
        $this->middleware = new MiddlewareStack();

        $this->configure();
    }

    private function configure(): void
    {
        $this->onBooting(function () {
            $this->define(RequestHandlerInterface::class, new RouterFactory($this->middleware, $this->routes))->share();
            $this->define(ServerRequestInterface::class, fn() => ServerRequestFactory::fromGlobals())->share();
        });
    }

    private function throwIf(bool $condition, string $message): void
    {
        if ($condition) {
            throw new KernelException($message);
        }
    }

    private function throwIfShutdown(): void
    {
        $this->throwIf($this->isShutdown(), 'Kernel is shutdown');
    }

    private function initRequestProfile(): void
    {
        if (!$this->isDebug()) {
            return;
        }

        $this->requestProfile = new Profiler();

        $this->requestProfile->start();
    }

    public function addRoute(array|string $methods, string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        $this->throwIf($this->isBooted(), 'Kernel has already booted, cannot add new routes');

        $methods = is_string($methods) ? [$methods] : $methods;

        return $this->routes->add($methods, $uri, $handler);
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): static
    {
        $this->throwIf($this->isBooted(), 'Kernel has already booted, cannot modify the global middleware stack');

        $this->middleware->add($middleware);

        return $this;
    }

    public function onTerminating(callable $callback): static
    {
        $this->throwIf($this->isBooted(), 'Kernel has already booted, cannot add on terminating callbacks');

        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->throwIfShutdown();

        $this->throwIf(!$this->isBooted(), 'Kernel cannot handle requests because it has not been booted');

        $ownsProfile = null === $this->requestProfile;

        if ($ownsProfile) {
            $this->initRequestProfile();
        }

        $this->requestProfile?->startPhase('handle');

        /** @var RequestHandlerInterface $handler */
        $handler = $this->getContainer()->get(RequestHandlerInterface::class);

        try {
            $this->requestProfile?->startPhase('middleware');

            $response = $handler->handle($request);

            $this->requestProfile?->stopPhase('middleware');
        } catch (Throwable $e) {
            $this->requestProfile?->stopPhase('middleware');

            $exceptionHandler = $this->getExceptionHandler();

            if (null === $exceptionHandler) {
                $this->requestProfile?->stopPhase('handle');

                if ($ownsProfile) {
                    $this->requestProfile?->stop();
                }

                throw $e;
            }

            $this->requestProfile?->startPhase('exceptionHandling');

            $response = $exceptionHandler->handle($e, $request);

            $this->requestProfile?->stopPhase('exceptionHandling');
        }

        $this->requestProfile?->stopPhase('handle');

        if ($ownsProfile) {
            $this->requestProfile?->stop();
        }

        return $response;
    }

    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        foreach ($this->terminatingCallbacks as $callback) {
            $callback($request, $response, $this);
        }
    }

    public function run(): int
    {
        $this->throwIfShutdown();

        $this->throwIf(!$this->isBooted(), 'Kernel cannot run because it has not been booted');

        $this->initRequestProfile();

        $this->requestProfile?->startPhase('requestResolution');

        /** @var ServerRequestInterface $request */
        $request = $this->getContainer()->get(ServerRequestInterface::class);

        $this->requestProfile?->stopPhase('requestResolution');

        $response = $this->handle($request);

        $this->requestProfile?->startPhase('emission');

        new SapiEmitter()->emit($response);

        $this->requestProfile?->stopPhase('emission');

        $this->requestProfile?->startPhase('terminate');

        $this->terminate($request, $response);

        $this->requestProfile?->stopPhase('terminate');

        $this->requestProfile?->stop();

        $this->shutdown();

        return 0;
    }

    public function getDebugInfo(): array
    {
        /** @var array<string, mixed> $info */
        $info = parent::getDebugInfo();

        if (null !== $this->requestProfile) {
            $info['requestProfile'] = $this->requestProfile->getDebugInfo();
        }

        return $info;
    }

    private function getExceptionHandler(): ?ExceptionHandlerInterface
    {
        return $this->getContainer()->has(ExceptionHandlerInterface::class)
            ? $this->getContainer()->get(ExceptionHandlerInterface::class)
            : null;
    }
}
