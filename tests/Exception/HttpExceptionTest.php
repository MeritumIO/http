<?php

namespace Meritum\Http\Test\Exception;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Exception\HttpException;
use Meritum\Http\Exception\HttpExceptionInterface;
use Meritum\Http\Exception\MethodNotAllowedHttpException;
use Meritum\Http\Exception\NotFoundHttpException;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

final class HttpExceptionTest extends TestCase
{
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        $this->request = new ServerRequest([], [], '/path', 'GET');
    }

    public function test_http_exception_implements_interface(): void
    {
        $e = new HttpException($this->request);

        $this->assertInstanceOf(HttpExceptionInterface::class, $e);
    }

    public function test_http_exception_default_status_code(): void
    {
        $e = new HttpException($this->request);

        $this->assertSame(500, $e->getStatusCode());
    }

    public function test_http_exception_default_title(): void
    {
        $e = new HttpException($this->request);

        $this->assertSame('Internal Server Error', $e->getTitle());
    }

    public function test_http_exception_stores_request(): void
    {
        $e = new HttpException($this->request);

        $this->assertSame($this->request, $e->getRequest());
    }

    public function test_http_exception_stores_message(): void
    {
        $e = new HttpException($this->request, 'Something went wrong');

        $this->assertSame('Something went wrong', $e->getMessage());
    }

    public function test_http_exception_stores_previous(): void
    {
        $previous = new \RuntimeException('original');
        $e        = new HttpException($this->request, '', $previous);

        $this->assertSame($previous, $e->getPrevious());
    }

    public function test_not_found_status_code(): void
    {
        $e = new NotFoundHttpException($this->request);

        $this->assertSame(404, $e->getStatusCode());
    }

    public function test_not_found_title(): void
    {
        $e = new NotFoundHttpException($this->request);

        $this->assertSame('Not Found', $e->getTitle());
    }

    public function test_not_found_stores_request(): void
    {
        $e = new NotFoundHttpException($this->request);

        $this->assertSame($this->request, $e->getRequest());
    }

    public function test_method_not_allowed_status_code(): void
    {
        $e = new MethodNotAllowedHttpException($this->request);

        $this->assertSame(405, $e->getStatusCode());
    }

    public function test_method_not_allowed_title(): void
    {
        $e = new MethodNotAllowedHttpException($this->request);

        $this->assertSame('Method Not Allowed', $e->getTitle());
    }

    public function test_method_not_allowed_stores_allowed_methods(): void
    {
        $e = new MethodNotAllowedHttpException($this->request, '', ['GET', 'POST']);

        $this->assertSame(['GET', 'POST'], $e->allowedMethods);
    }

    public function test_method_not_allowed_allowed_methods_defaults_to_empty(): void
    {
        $e = new MethodNotAllowedHttpException($this->request);

        $this->assertSame([], $e->allowedMethods);
    }

    public function test_method_not_allowed_stores_request(): void
    {
        $e = new MethodNotAllowedHttpException($this->request);

        $this->assertSame($this->request, $e->getRequest());
    }
}
