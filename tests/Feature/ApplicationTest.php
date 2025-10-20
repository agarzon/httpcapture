<?php

declare(strict_types=1);

namespace HttpCapture\Tests\Feature;

use HttpCapture\Application;
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
}
