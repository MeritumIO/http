<?php

namespace Meritum\Http\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Meritum\Http\Exception\MiddlewareStackException;

final class MiddlewareResolver
{
    public function __construct(private readonly ContainerInterface $container) {}

    public function __invoke(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        if (is_string($middleware)) {
            $str = $middleware;

            try {
                $middleware = $this->container->get($middleware);
            } catch (\Throwable $e) {
                MiddlewareStackException::throw($e->getMessage(), $e);
            }

            MiddlewareStackException::throwIfNot(
                $middleware instanceof MiddlewareInterface,
                sprintf(
                    'Invalid middleware entry [%s], middleware must implement %s',
                    $str,
                    MiddlewareInterface::class
                )
            );
        }

        /** @var MiddlewareInterface $middleware */
        return $middleware;
    }
}
