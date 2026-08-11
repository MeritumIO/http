<?php

namespace Meritum\Http;

use Throwable;
use Georgeff\Kernel\Kernel;
use Georgeff\Kernel\KernelInterface;
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
use Georgeff\Kernel\Exception\KernelException;
use Georgeff\Kernel\Contract\EnvironmentInterface;
use Meritum\Http\Contract\ExceptionHandlerInterface;
use Georgeff\Kernel\Contract\ContainerBuilderInterface;

final class HttpKernel extends Kernel implements HttpKernelInterface
{
    private readonly RouteCollection $routes;

    private readonly MiddlewareStack $middleware;

    /**
     * @var array<int, callable(ServerRequestInterface, ResponseInterface, KernelInterface): void>
     */
    private array $terminatingCallbacks = [];

    public function __construct(EnvironmentInterface $environment, ?ContainerBuilderInterface $builder = null, bool $debug = false)
    {
        parent::__construct($environment, $builder, $debug);

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

        $ownsProfile
            = null !== $this->profiler
            && false === $this->profiler->hasProfile('request');

        $profile = $ownsProfile
            ? $this->profiler->initProfile('request')
            : $this->profiler?->getProfile('request');

        $profile?->startPhase('handle');

        /** @var RequestHandlerInterface $handler */
        $handler = $this->getContainer()->get(RequestHandlerInterface::class);

        try {
            $profile?->startPhase('middleware');

            $response = $handler->handle($request);

            $profile?->stopPhase('middleware');
        } catch (Throwable $e) {
            $profile?->stopPhase('middleware');

            $exceptionHandler = $this->getExceptionHandler();

            if (null === $exceptionHandler) {
                $profile?->stopPhase('handle');

                if ($ownsProfile) {
                    $profile?->stop();
                }

                throw $e;
            }

            $profile?->startPhase('exceptionHandling');

            $response = $exceptionHandler->handle($e, $request);

            $profile?->stopPhase('exceptionHandling');
        }

        $profile?->stopPhase('handle');

        if ($ownsProfile) {
            $profile?->stop();
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

        $profile = $this->profiler?->initProfile('request');

        $profile?->startPhase('requestResolution');

        /** @var ServerRequestInterface $request */
        $request = $this->getContainer()->get(ServerRequestInterface::class);

        $profile?->stopPhase('requestResolution');

        $response = $this->handle($request);

        $profile?->startPhase('emission');

        new SapiEmitter()->emit($response);

        $profile?->stopPhase('emission');

        $profile?->startPhase('terminate');

        $this->terminate($request, $response);

        $profile?->stopPhase('terminate');

        $profile?->stop();

        $this->shutdown();

        return 0;
    }

    private function getExceptionHandler(): ?ExceptionHandlerInterface
    {
        return $this->getContainer()->has(ExceptionHandlerInterface::class)
            ? $this->getContainer()->get(ExceptionHandlerInterface::class)
            : null;
    }
}
