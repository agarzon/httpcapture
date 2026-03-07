<?php

declare(strict_types=1);

namespace HttpCapture\Formatter;

final class MarkdownFormatter
{
    private const REDACTED_HEADERS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    private const REDACTED_VALUE = '[REDACTED]';
    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $meta
     */
    public function formatList(array $items, array $meta): string
    {
        $page = $meta['page'] ?? 1;
        $lastPage = $meta['last_page'] ?? 1;
        $total = $meta['total'] ?? 0;

        $lines = ["# Captured Requests (Page {$page} of {$lastPage}, {$total} total)", ''];

        foreach ($items as $i => $item) {
            if ($i > 0) {
                $lines[] = '---';
                $lines[] = '';
            }
            $lines[] = $this->formatItem($item);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $item
     */
    public function formatSingle(array $item): string
    {
        return $this->formatItem($item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function formatItem(array $item): string
    {
        $id = $item['id'] ?? '?';
        $method = $item['method'] ?? 'UNKNOWN';
        $path = $item['path'] ?? '/';
        $createdAt = $item['created_at'] ?? '';
        $clientIp = $item['client_ip'] ?? '';
        $fullUrl = $item['full_url'] ?? '';

        $lines = [
            "## #{$id} — {$method} {$path}",
            "- **Time:** {$createdAt}",
            "- **IP:** {$clientIp}",
            "- **URL:** {$fullUrl}",
        ];

        // Query Parameters
        $queryParams = $this->decodeJsonField($item['query_params'] ?? null);
        if (!empty($queryParams)) {
            $lines[] = '';
            $lines[] = '### Query Parameters';
            $lines[] = '| Parameter | Value |';
            $lines[] = '|-----------|-------|';
            foreach ($queryParams as $key => $value) {
                $lines[] = "| {$key} | {$this->escapeCell($value)} |";
            }
        }

        // Headers
        $headers = $this->decodeJsonField($item['headers'] ?? null);
        if (!empty($headers)) {
            $lines[] = '';
            $lines[] = '### Headers';
            $lines[] = '| Header | Value |';
            $lines[] = '|--------|-------|';
            foreach ($headers as $key => $value) {
                $displayValue = $this->isSensitiveHeader($key) ? self::REDACTED_VALUE : $this->escapeCell($value);
                $lines[] = "| {$key} | {$displayValue} |";
            }
        }

        // Body
        $body = $item['body'] ?? '';
        if ($body !== '' && $body !== null) {
            $lang = $this->detectLanguage((string) $body);
            $lines[] = '';
            $lines[] = '### Body';
            $lines[] = "```{$lang}";
            $lines[] = (string) $body;
            $lines[] = '```';
        }

        // Form Data
        $formData = $this->decodeJsonField($item['form_data'] ?? null);
        if (!empty($formData)) {
            $lines[] = '';
            $lines[] = '### Form Data';
            $lines[] = '| Field | Value |';
            $lines[] = '|-------|-------|';
            foreach ($formData as $key => $value) {
                $lines[] = "| {$key} | {$this->escapeCell($value)} |";
            }
        }

        // Files
        $files = $this->decodeJsonField($item['files'] ?? null);
        if (!empty($files)) {
            $lines[] = '';
            $lines[] = '### Files';
            $lines[] = '| Field | Name | Type | Size |';
            $lines[] = '|-------|------|------|------|';
            foreach ($files as $field => $info) {
                if (is_array($info)) {
                    $name = $info['name'] ?? '';
                    $type = $info['type'] ?? '';
                    $size = $info['size'] ?? 0;
                    $lines[] = "| {$field} | {$name} | {$type} | {$size} |";
                }
            }
        }

        return implode("\n", $lines);
    }

    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function detectLanguage(string $body): string
    {
        $trimmed = ltrim($body);
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return 'json';
        }
        if (str_starts_with($trimmed, '<')) {
            return 'xml';
        }
        return '';
    }

    private function isSensitiveHeader(string $name): bool
    {
        $normalized = strtolower($name);

        if (in_array($normalized, self::REDACTED_HEADERS, true)) {
            return true;
        }

        $compact = str_replace(['-', '_'], '', $normalized);
        $patterns = [
            'authorization',
            'cookie',
            'token',
            'secret',
            'apikey',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($compact, $pattern)) {
                return true;
            }
        }

        return str_ends_with($compact, 'key');
    }

    private function escapeCell(mixed $value): string
    {
        if (is_array($value)) {
            return (string) json_encode($value);
        }
        return str_replace('|', '\\|', (string) $value);
    }
}
