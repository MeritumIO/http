<?php

namespace Meritum\Http\Test\Emitter;

use Laminas\Diactoros\Response;
use Meritum\Http\Emitter\SapiEmitter;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class SapiEmitterTest extends TestCase
{
    private function createResponseWithBody(string $content, int $status = 200): Response
    {
        $response = new Response('php://temp', $status);
        $response->getBody()->write($content);

        return $response;
    }

    private function emit(Response $response, int $chunkSize = 4096): string
    {
        ob_start();
        (new SapiEmitter($chunkSize))->emit($response);

        return (string) ob_get_clean();
    }

    public function test_emits_body_content(): void
    {
        $response = $this->createResponseWithBody('Hello World');

        $this->assertSame('Hello World', $this->emit($response));
    }

    public function test_rewinds_seekable_body_before_emitting(): void
    {
        $response = $this->createResponseWithBody('Hello World');
        $response->getBody()->seek(6);

        $this->assertSame('Hello World', $this->emit($response));
    }

    public function test_does_not_emit_body_for_204_response(): void
    {
        $response = $this->createResponseWithBody('Should not appear', 204);

        $this->assertSame('', $this->emit($response));
    }

    public function test_does_not_emit_body_for_205_response(): void
    {
        $response = $this->createResponseWithBody('Should not appear', 205);

        $this->assertSame('', $this->emit($response));
    }

    public function test_does_not_emit_body_for_304_response(): void
    {
        $response = $this->createResponseWithBody('Should not appear', 304);

        $this->assertSame('', $this->emit($response));
    }

    public function test_does_not_emit_body_for_empty_seekable_body(): void
    {
        $response = new Response('php://temp', 200);

        $this->assertSame('', $this->emit($response));
    }

    public function test_respects_content_length_header(): void
    {
        $response = $this->createResponseWithBody('Hello World')
            ->withHeader('Content-Length', '5');

        $this->assertSame('Hello', $this->emit($response));
    }

    public function test_emits_body_in_chunks_with_custom_chunk_size(): void
    {
        $content  = str_repeat('x', 100);
        $response = $this->createResponseWithBody($content);

        $this->assertSame($content, $this->emit($response, 10));
    }

    public function test_emits_body_unbounded_when_size_unknown(): void
    {
        $stream = new class('Hello World') implements StreamInterface {
            private int $pos = 0;

            public function __construct(private readonly string $data) {}

            public function __toString(): string { return $this->data; }
            public function close(): void {}
            public function detach(): mixed { return null; }
            public function getSize(): ?int { return null; }
            public function tell(): int { return $this->pos; }
            public function eof(): bool { return $this->pos >= strlen($this->data); }
            public function isSeekable(): bool { return false; }
            public function seek(int $offset, int $whence = SEEK_SET): void { throw new \RuntimeException('Not seekable'); }
            public function rewind(): void { throw new \RuntimeException('Not seekable'); }
            public function isWritable(): bool { return false; }
            public function write(string $string): int { throw new \RuntimeException('Not writable'); }
            public function isReadable(): bool { return true; }
            public function read(int $length): string
            {
                $chunk = substr($this->data, $this->pos, $length);
                $this->pos += strlen($chunk);

                return $chunk;
            }
            public function getContents(): string { return substr($this->data, $this->pos); }
            public function getMetadata(?string $key = null): mixed { return null; }
        };

        $response = new Response($stream, 200);

        $this->assertSame('Hello World', $this->emit($response));
    }
}
