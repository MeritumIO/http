<?php

namespace Meritum\Http\Contract;

use Psr\Http\Message\ResponseInterface;

interface EmitterInterface
{
    public function emit(ResponseInterface $response): void;
}
