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
use Meritum\Http\Contract\EmitterInterface;
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

    private bool $handled = false;

    public function __construct(EnvironmentInterface $environment, ?ContainerBuilderInterface $builder = null, bool $debug = false)
    {
        parent::__construct($environment, $builder, $debug);

        $this->routes     = new RouteCollection();
        $this->middleware = new MiddlewareStack();

        $this->configure();

        $this->profiler?->register($this->middleware, 'middleware');
        $this->profiler?->register($this->routes, 'routes');
    }

    private function configure(): void
    {
        $this->onBooting(function () {
            $this->define(EmitterInterface::class, fn() => new SapiEmitter())->share();
            $this->define(RequestHandlerInterface::class, new RouterFactory($this->middleware, $this->routes))->share();
            $this->define(ServerRequestInterface::class, fn() => ServerRequestFactory::fromGlobals())->share();
        });
    }

    public function addRoute(array|string $methods, string $uri, RequestHandlerInterface|string $handler): RouteInterface
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already booted, cannot add new routes');

        $methods = is_string($methods) ? [$methods] : $methods;

        return $this->routes->add($methods, $uri, $handler);
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): static
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already booted, cannot add middleware to the global stack');

        $this->middleware->add($middleware);

        return $this;
    }

    public function onTerminating(callable $callback): static
    {
        KernelException::throwIf($this->isBooted(), 'Kernel has already booted, cannot add terminating callbacks');

        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        KernelException::throwIf($this->isShutdown(), 'Kernel is shutdown');

        KernelException::throwIfNot($this->isBooted(), 'Kernel cannot handle requests because it has not been booted');

        $this->handled = false;

        $profile = $this->profiler?->initProfile('handle');

        $handler = $this->getContainer()->get(RequestHandlerInterface::class);

        try {
            $profile?->startPhase('middleware');

            $response = $handler->handle($request);

            $profile?->stopPhase('middleware');
        } catch (Throwable $e) {
            $profile?->stopPhase('middleware');

            $exceptionHandler = $this->getExceptionHandler();

            if (null === $exceptionHandler) {
                $profile?->stop();

                throw $e;
            }

            $profile?->startPhase('exceptionHandling');

            $response = $exceptionHandler->handle($e, $request);

            $profile?->stopPhase('exceptionHandling');
        }

        $this->handled = true;

        $profile?->stop();

        return $response;
    }

    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        KernelException::throwIfNot($this->handled, 'Cannot terminate an unhandled request');

        $profile = $this->profiler?->initProfile('terminate');

        foreach ($this->terminatingCallbacks as $callback) {
            $callback($request, $response, $this);
        }

        $profile?->stop();
    }

    public function run(): int
    {
        KernelException::throwIf($this->isShutdown(), 'Kernel is shutdown');

        $this->boot();

        $profile = $this->profiler?->initProfile('run');

        $profile?->startPhase('requestResolution');

        $request = $this->getContainer()->get(ServerRequestInterface::class);

        $profile?->stopPhase('requestResolution');

        $response = $this->handle($request);

        $profile?->startPhase('emission');

        $emitter = $this->getContainer()->get(EmitterInterface::class);

        $emitter->emit($response);

        $profile?->stopPhase('emission');

        $profile?->stop();

        $this->terminate($request, $response);

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
