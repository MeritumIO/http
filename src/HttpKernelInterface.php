<?php

namespace Meritum\Http;

use Georgeff\Kernel\KernelInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Meritum\Http\Routing\RouteRegistrationInterface;
use Meritum\Http\Contract\ExceptionHandlerInterface;
use Georgeff\Kernel\Contract\RunnableKernelInterface;

interface HttpKernelInterface extends RunnableKernelInterface, RequestHandlerInterface, RouteRegistrationInterface
{
    /**
     * Add a middleware to the global stack
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): static;

    /**
     * Add an exception handler
     *
     * @param callable(ContainerInterface $container): ExceptionHandlerInterface $factory
     */
    public function addExceptionHandler(callable $factory): static;

    /**
     * Register a terminating callback
     *
     * @param callable(ServerRequestInterface, ResponseInterface, KernelInterface): void $callback
     */
    public function onTerminating(callable $callback): static;

    /**
     * Terminate a request/response cycle
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void;
}
