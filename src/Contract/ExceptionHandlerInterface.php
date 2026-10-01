<?php

namespace Meritum\Http\Contract;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ExceptionHandlerInterface
{
    /**
     * Produce the response to send for an exception that occurred while processing a request
     */
    public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface;
}
