<?php

declare(strict_types=1);

namespace HttpCapture\Http;

final class Response
{
    private string $body;
    private int $statusCode;
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $body, int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(array $payload, int $statusCode = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'application/json'], $headers);

        return new self((string) json_encode($payload, JSON_PRETTY_PRINT), $statusCode, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $body, int $statusCode = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers);

        return new self($body, $statusCode, $headers);
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
