<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $queryParams;
    private array $parsedBody;
    private array $headers;
    private array $attributes = [];
    private string $method;
    private string $uri;
    private string $body;

    public function __construct(
        string $method,
        string $uri,
        array $queryParams = [],
        array $parsedBody = [],
        array $headers = [],
        string $body = ''
    ) {
        $this->method = $method;
        $this->uri = $uri;
        $this->queryParams = $queryParams;
        $this->parsedBody = $parsedBody;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

    // Query Params
        parse_str($_SERVER['QUERY_STRING'] ?? '', $queryParams);

    // Body
        $input = file_get_contents('php://input');
        $parsedBody = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            $parsedBody = json_decode($input, true) ?? [];
        } else {
            $parsedBody = $_POST;
        }

    // Headers
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }

        return new self($method, $uri, $queryParams, $parsedBody, $headers, $input ?: '');
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getParsedBody(): array|null
    {
        return $this->parsedBody;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getHeaderLine(string $name): string
    {
        // Case-insensitive header search
        $name = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }
        return '';
    }

    public function withAttribute(string $name, $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }
}
