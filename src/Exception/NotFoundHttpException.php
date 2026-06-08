<?php

namespace Meritum\Http\Exception;

final class NotFoundHttpException extends HttpException
{
    protected string $title = 'Not Found';

    protected int $status = 404;
}
