<?php

declare(strict_types=1);

namespace HttpCapture\Http;

final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    /** @var array<string, mixed> */
    private array $query;
    /** @var array<string, string> */
    private array $headers;
    private string $body;
    private string $clientIp;
    /** @var array<string, mixed> */
    private array $server;
    /** @var array<string, mixed> */
    private array $parsedBody;
    /** @var array<string, mixed> */
    private array $uploadedFiles;

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public function __construct(array $server, string $method, string $uri, string $path, array $query, array $headers, string $body, string $clientIp, array $parsedBody, array $uploadedFiles)
    {
        $this->server = $server;
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = $path;
        $this->query = $query;
        $this->headers = $headers;
        $this->body = $body;
        $this->clientIp = $clientIp;
        $this->parsedBody = $parsedBody;
        $this->uploadedFiles = $uploadedFiles;
    }

    /**
     * @param array<string, mixed> $server
     */
    public static function fromGlobals(array $server, string $rawBody, array $queryParams = [], array $postParams = [], array $files = []): self
    {
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $uri = $server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($uri, PHP_URL_QUERY) ?: '';
        parse_str($queryString, $query);

        if (!empty($queryParams)) {
            $query = array_merge($query, $queryParams);
        }

        $headers = self::extractHeaders($server);
        $clientIp = self::determineClientIp($server, $headers);
        $parsedBody = $postParams;
        $uploadedFiles = self::normalizeFilesArray($files);

        return new self($server, $method, $uri, $path, $query, $headers, $rawBody, $clientIp, $parsedBody, $uploadedFiles);
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $server['CONTENT_TYPE'];
        }

        if (isset($server['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = (string) $server['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, string> $headers
     */
    private static function determineClientIp(array $server, array $headers): string
    {
        $forwarded = $headers['X-Forwarded-For'] ?? $headers['Forwarded'] ?? '';

        if (!empty($forwarded)) {
            if (str_contains($forwarded, ',')) {
                $forwarded = trim(explode(',', $forwarded)[0]);
            }
            $forwarded = trim($forwarded);
            if ($forwarded !== '') {
                return $forwarded;
            }
        }

        if (!empty($headers['Cf-Connecting-Ip'])) {
            return $headers['Cf-Connecting-Ip'];
        }

        $remoteAddr = $server['REMOTE_ADDR'] ?? '0.0.0.0';

        return is_string($remoteAddr) ? $remoteAddr : '0.0.0.0';
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return $this->server;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private static function normalizeFilesArray(array $files): array
    {
        $normalized = [];

        foreach ($files as $field => $info) {
            if (!is_array($info)) {
                continue;
            }

            if (isset($info['name']) && !is_array($info['name'])) {
                $normalized[$field] = self::punchFileLeaf($info);
                continue;
            }

            $normalized[$field] = self::normalizeFilesRecursive($info);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private static function normalizeFilesRecursive(array $file): array
    {
        $normalized = [];

        $names = $file['name'] ?? [];
        $types = $file['type'] ?? [];
        $tmpNames = $file['tmp_name'] ?? [];
        $errors = $file['error'] ?? [];
        $sizes = $file['size'] ?? [];

        $keys = array_keys((array) $names);

        foreach ($keys as $key) {
            if (is_array($names[$key] ?? null)) {
                $normalized[$key] = self::normalizeFilesRecursive([
                    'name' => $names[$key] ?? null,
                    'type' => $types[$key] ?? null,
                    'tmp_name' => $tmpNames[$key] ?? null,
                    'error' => $errors[$key] ?? null,
                    'size' => $sizes[$key] ?? null,
                ]);
                continue;
            }

            $normalized[$key] = self::punchFileLeaf([
                'name' => $names[$key] ?? null,
                'type' => $types[$key] ?? null,
                'tmp_name' => $tmpNames[$key] ?? null,
                'error' => $errors[$key] ?? null,
                'size' => $sizes[$key] ?? null,
            ]);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private static function punchFileLeaf(array $file): array
    {
        return [
            'name' => (string) ($file['name'] ?? ''),
            'type' => (string) ($file['type'] ?? ''),
            'size' => (int) ($file['size'] ?? 0),
            'error' => (int) ($file['error'] ?? 0),
        ];
    }
}
