<?php

namespace Meritum\Http\Contract;

use Psr\Http\Message\ResponseInterface;

interface EmitterInterface
{
    /**
     * Send the response to the client
     */
    public function emit(ResponseInterface $response): void;
}
