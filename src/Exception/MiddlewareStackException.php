<?php

namespace Meritum\Http\Exception;

use Georgeff\Kernel\Exception\ThrowHelpers;
use Georgeff\Kernel\Exception\KernelExceptionInterface;

final class MiddlewareStackException extends \RuntimeException implements KernelExceptionInterface
{
    use ThrowHelpers;
}
