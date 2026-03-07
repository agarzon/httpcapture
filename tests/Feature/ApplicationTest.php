<?php

declare(strict_types=1);

namespace HttpCapture\Tests\Feature;

use HttpCapture\Application;
use HttpCapture\Http\RequestFilter;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/httpcapture_test.sqlite';
        @unlink($this->databasePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->databasePath);

        parent::tearDown();
    }

    public function testItCapturesRequestAndListsIt(): void
    {
        $app = new Application($this->databasePath);

        $captureResponse = $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/webhook',
            'HTTP_HOST' => 'example.test',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            'REMOTE_ADDR' => '10.0.0.5',
        ], '{"status":"ok"}');

        $this->assertSame(201, $captureResponse->getStatusCode());

        $payload = json_decode($captureResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Request captured', $payload['message']);
        $this->assertSame('/webhook', $payload['data']['path']);
        $this->assertSame('203.0.113.9', $payload['data']['client_ip']);

        $listResponse = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests',
        ], '');

        $this->assertSame(200, $listResponse->getStatusCode());

        $listPayload = json_decode($listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $listPayload['meta']['count']);
        $this->assertSame('/webhook', $listPayload['data'][0]['path']);
    }

    public function testFrontendRoutesReturnHtml(): void
    {
        $app = new Application($this->databasePath);

        $uiResponse = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ], '');

        $this->assertSame(200, $uiResponse->getStatusCode());
        $this->assertStringContainsString('<!DOCTYPE html>', $uiResponse->getBody());

        $listResponse = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests',
        ], '');

        $payload = json_decode($listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(0, $payload['meta']['count']);
    }

    public function testDeleteAllTruncatesDatabase(): void
    {
        $app = new Application($this->databasePath);

        $firstResponse = $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/capture-one',
            'HTTP_HOST' => 'example.test',
        ], 'first');

        $firstPayload = json_decode($firstResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(201, $firstResponse->getStatusCode());
        $this->assertSame(1, $firstPayload['data']['id']);

        $deleteResponse = $app->handle([
            'REQUEST_METHOD' => 'DELETE',
            'REQUEST_URI' => '/api/requests',
        ], '');
        $deletePayload = json_decode($deleteResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $deleteResponse->getStatusCode());
        $this->assertSame('All requests cleared', $deletePayload['message']);

        $secondResponse = $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/capture-two',
            'HTTP_HOST' => 'example.test',
        ], 'second');

        $secondPayload = json_decode($secondResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(201, $secondResponse->getStatusCode());
        $this->assertSame(1, $secondPayload['data']['id']);
    }

    public function testMultipartFormBodyIsCaptured(): void
    {
        $app = new Application($this->databasePath);

        $response = $app->handle(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/forms',
                'HTTP_HOST' => 'example.test',
                'CONTENT_TYPE' => 'multipart/form-data; boundary=----WebKitFormBoundary',
            ],
            '',
            [],
            ['name' => 'Alice', 'age' => '30'],
            [
                'avatar' => [
                    'name' => 'avatar.png',
                    'type' => 'image/png',
                    'size' => 512,
                    'error' => 0,
                ],
            ]
        );

        $this->assertSame(201, $response->getStatusCode());
        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('form_data', $payload['data']);
        $this->assertSame('Alice', $payload['data']['form_data']['name']);
        $this->assertSame('avatar.png', $payload['data']['files']['avatar']['name']);

        $jsonBody = json_decode($payload['data']['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('30', $jsonBody['form']['age']);
    }

    public function testPaginationMetadata(): void
    {
        $app = new Application($this->databasePath);

        foreach (range(1, 3) as $i) {
            $app->handle([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/page-test-' . $i,
                'HTTP_HOST' => 'example.test',
            ], 'body-' . $i);
        }

        $listResponse = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests?page=1&per_page=2',
        ], '', ['page' => '1', 'per_page' => '2']);

        $this->assertSame(200, $listResponse->getStatusCode());
        $payload = json_decode($listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, count($payload['data']));
        $this->assertSame(3, $payload['meta']['total']);
        $this->assertSame(2, $payload['meta']['last_page']);
        $this->assertSame(2, $payload['meta']['per_page']);
    }

    public function testGetFallbackStoresAndReturnsOk(): void
    {
        $app = new Application($this->databasePath);

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/healthz',
            'HTTP_HOST' => 'example.test',
        ], '');

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('OK', $payload['message']);

        $listResponse = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests',
        ], '');

        $listPayload = json_decode($listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('/healthz', $listPayload['data'][0]['path']);
        $this->assertSame('GET', $listPayload['data'][0]['method']);
        $this->assertSame('', $listPayload['data'][0]['body']);
    }

    public function testApiPrefixLookalikePathsAreStillCaptured(): void
    {
        $app = new Application($this->databasePath);

        $response = $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/apiary',
            'HTTP_HOST' => 'example.test',
        ], 'payload');

        $this->assertSame(201, $response->getStatusCode());

        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('/apiary', $payload['data']['path']);
    }

    public function testDefaultFilterIgnoresFaviconRequests(): void
    {
        $app = new Application($this->databasePath);

        $faviconResponse = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/favicon.ico',
            'HTTP_HOST' => 'example.test',
        ], '');

        $this->assertSame(204, $faviconResponse->getStatusCode());
        $this->assertSame('', $faviconResponse->getBody());

        $listResponse = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests',
        ], '');

        $payload = json_decode($listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(0, $payload['meta']['count']);
    }

    public function testCustomFilterRulesCanSkipAdditionalTraffic(): void
    {
        $filter = RequestFilter::create()->ignorePathPrefix('/internal');
        $app = new Application($this->databasePath, $filter);

        $ignored = $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/internal/ping',
            'HTTP_HOST' => 'example.test',
        ], 'hello');

        $this->assertSame(204, $ignored->getStatusCode());

        $captured = $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/webhook',
            'HTTP_HOST' => 'example.test',
        ], 'payload');

        $this->assertSame(201, $captured->getStatusCode());

        $listResponse = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests',
        ], '');

        $payload = json_decode($listResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $payload['meta']['count']);
        $this->assertSame('/webhook', $payload['data'][0]['path']);
    }

    public function testForwardedHeaderUsesForValueAsClientIp(): void
    {
        $app = new Application($this->databasePath);

        $response = $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/proxy-test',
            'HTTP_HOST' => 'example.test',
            'HTTP_FORWARDED' => 'for=203.0.113.10;proto=https;by=203.0.113.20',
            'REMOTE_ADDR' => '10.0.0.5',
        ], 'payload');

        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('203.0.113.10', $payload['data']['client_ip']);
    }

    public function testMarkdownListResponse(): void
    {
        $app = new Application($this->databasePath);

        $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/webhook',
            'HTTP_HOST' => 'example.test',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], '{"status":"ok"}');

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests',
            'HTTP_ACCEPT' => 'text/markdown',
        ], '');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/markdown; charset=UTF-8', $response->getHeaders()['Content-Type']);
        $this->assertStringContainsString('# Captured Requests', $response->getBody());
        $this->assertStringContainsString('## #', $response->getBody());
        $this->assertStringContainsString('### Headers', $response->getBody());
    }

    public function testMarkdownSingleResponse(): void
    {
        $app = new Application($this->databasePath);

        $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test-endpoint',
            'HTTP_HOST' => 'example.test',
        ], '{"hello":"world"}');

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests/1',
            'HTTP_ACCEPT' => 'text/markdown',
        ], '');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/markdown; charset=UTF-8', $response->getHeaders()['Content-Type']);
        $this->assertStringContainsString('## #1', $response->getBody());
        $this->assertStringContainsString('POST /test-endpoint', $response->getBody());
        $this->assertStringContainsString('### Body', $response->getBody());
    }

    public function testMarkdownSingleNotFoundResponse(): void
    {
        $app = new Application($this->databasePath);

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests/999',
            'HTTP_ACCEPT' => 'text/markdown',
        ], '');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('text/markdown; charset=UTF-8', $response->getHeaders()['Content-Type']);
        $this->assertSame("Request not found.\n", $response->getBody());
    }

    public function testMarkdownApiRouteNotFoundResponse(): void
    {
        $app = new Application($this->databasePath);

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/unknown',
            'HTTP_ACCEPT' => 'text/markdown',
        ], '');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('text/markdown; charset=UTF-8', $response->getHeaders()['Content-Type']);
        $this->assertSame("Route not found.\n", $response->getBody());
    }

    public function testJsonStillDefaultWithoutAcceptHeader(): void
    {
        $app = new Application($this->databasePath);

        $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/webhook',
            'HTTP_HOST' => 'example.test',
        ], 'data');

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests',
        ], '');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);

        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('meta', $payload);
    }

    public function testMarkdownRedactsSensitiveHeaders(): void
    {
        $app = new Application($this->databasePath);

        $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/secret-test',
            'HTTP_HOST' => 'example.test',
            'HTTP_AUTHORIZATION' => 'Bearer super-secret-token',
            'HTTP_X_API_KEY' => 'sk-12345',
            'HTTP_COOKIE' => 'session=abc123',
            'HTTP_X_CUSTOM' => 'visible-value',
        ], '{}');

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests/1',
            'HTTP_ACCEPT' => 'text/markdown',
        ], '');

        $body = $response->getBody();
        $this->assertStringContainsString('[REDACTED]', $body);
        $this->assertStringNotContainsString('super-secret-token', $body);
        $this->assertStringNotContainsString('sk-12345', $body);
        $this->assertStringNotContainsString('abc123', $body);
        $this->assertStringContainsString('visible-value', $body);

        // Verify JSON still shows full values
        $jsonResponse = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests/1',
        ], '');

        $jsonBody = $jsonResponse->getBody();
        $this->assertStringContainsString('super-secret-token', $jsonBody);
        $this->assertStringContainsString('sk-12345', $jsonBody);
    }

    public function testMarkdownRedactsSensitiveHeaderVariants(): void
    {
        $app = new Application($this->databasePath);

        $app->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/secret-variants',
            'HTTP_HOST' => 'example.test',
            'HTTP_API_KEY' => 'api-key-value',
            'HTTP_X_FORWARDED_ACCESS_TOKEN' => 'access-token-value',
            'HTTP_X_AUTH_SECRET' => 'auth-secret-value',
            'HTTP_X_TRACE_ID' => 'trace-visible',
        ], '{}');

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests/1',
            'HTTP_ACCEPT' => 'text/markdown',
        ], '');

        $body = $response->getBody();
        $this->assertStringContainsString('| Api-Key | [REDACTED] |', $body);
        $this->assertStringContainsString('| X-Forwarded-Access-Token | [REDACTED] |', $body);
        $this->assertStringContainsString('| X-Auth-Secret | [REDACTED] |', $body);
        $this->assertStringContainsString('| X-Trace-Id | trace-visible |', $body);
        $this->assertStringNotContainsString('api-key-value', $body);
        $this->assertStringNotContainsString('access-token-value', $body);
        $this->assertStringNotContainsString('auth-secret-value', $body);
    }

    public function testMarkdownOmitsEmptySections(): void
    {
        $app = new Application($this->databasePath);

        // GET request — no body, no form_data, no files
        $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/simple',
            'HTTP_HOST' => 'example.test',
        ], '');

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/requests/1',
            'HTTP_ACCEPT' => 'text/markdown',
        ], '');

        $body = $response->getBody();
        $this->assertStringNotContainsString('### Body', $body);
        $this->assertStringNotContainsString('### Form Data', $body);
        $this->assertStringNotContainsString('### Files', $body);
    }
}
