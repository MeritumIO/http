<?php

namespace Meritum\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

final class SapiEmitter
{
    public function __construct(private int $responseChunkSize = 4096) {}

    public function emit(ResponseInterface $response): void
    {
        if (false === headers_sent()) {
            $this->emitHeaders($response);

            $this->emitStatusLine($response);
        }

        if (!$this->isResponseEmpty($response)) {
            $this->emitBody($response);
        }
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $first = 'set-cookie' !== strtolower($name);

            foreach ($values as $value) {
                $header = "{$name}: {$value}";

                header($header, $first);

                $first = false;
            }
        }
    }

    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusLine = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );

        header($statusLine, true, $response->getStatusCode());
    }

    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $amountToRead = (int) $response->getHeaderLine('Content-Length');

        if (0 === $amountToRead) {
            $amountToRead = (int) $body->getSize();
        }

        if ($amountToRead) {
            while ($amountToRead > 0 && !$body->eof()) {
                $length = min($this->responseChunkSize, $amountToRead);

                $data = $body->read($length);

                echo $data;

                $amountToRead -= strlen($data);

                if (CONNECTION_NORMAL !== connection_status()) {
                    break;
                }
            }
        } else {
            while (!$body->eof()) {
                echo $body->read($this->responseChunkSize);

                if (CONNECTION_NORMAL !== connection_status()) {
                    break;
                }
            }
        }
    }

    private function isResponseEmpty(ResponseInterface $response): bool
    {
        if (in_array($response->getStatusCode(), [204, 205, 304], true)) {
            return true;
        }

        $body = $response->getBody();
        $seekable = $body->isSeekable();

        if ($seekable) {
            $body->rewind();
        }

        return $seekable ? '' === $body->read(1) : $body->eof();
    }
}
