<?php

namespace Meritum\Http\Contract;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ExceptionHandlerInterface
{
    public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface;
}
