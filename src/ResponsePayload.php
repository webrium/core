<?php

declare(strict_types=1);

namespace Webrium;

/**
 * A self-describing HTTP response.
 *
 * A ResponsePayload carries the body, the HTTP status code, and the exact set
 * of headers to emit. The helpers html(), json() and text() build an instance
 * with the right Content-Type already attached, so Header::respond() does not
 * need to know anything about formats — it simply emits the headers and echoes
 * the body. This keeps respond() closed for modification: new response kinds
 * (images, downloads, streams, ...) are added by composing a payload, never by
 * editing respond().
 *
 * If no header is added, none is sent — respond() emits only the status and
 * body, so a "raw" response is just a payload with no headers attached.
 *
 * @package Webrium
 */
class ResponsePayload
{
    /**
     * @param string $body       The already-serialised response body.
     * @param int    $statusCode HTTP status code to send.
     * @param array<string,string> $headers Header name => value pairs to emit.
     */
    public function __construct(
        private string $body = '',
        private int $statusCode = 200,
        private array $headers = [],
    ) {
    }

    /**
     * Attach (or replace) a header on this payload. Returns $this for chaining.
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set the HTTP status code. Returns $this for chaining.
     */
    public function withStatus(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
