<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    private Stream $body;
    private int $statusCode = 200;
    private array $headers = [];

    public function __construct()
    {
        $this->body = new Stream();
    }

    public function getBody(): Stream
    {
        return $this->body;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withStatus(int $code): self
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
