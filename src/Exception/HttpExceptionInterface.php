<?php

namespace Meritum\Http\Exception;

use Psr\Http\Message\ServerRequestInterface;

interface HttpExceptionInterface extends \Throwable
{
    public function getRequest(): ServerRequestInterface;

    public function getTitle(): string;

    public function getStatusCode(): int;
}
