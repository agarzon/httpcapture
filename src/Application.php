<?php

declare(strict_types=1);

namespace HttpCapture;

use HttpCapture\Controller\CaptureController;
use HttpCapture\Controller\RequestsController;
use HttpCapture\Formatter\MarkdownFormatter;
use HttpCapture\Http\Request;
use HttpCapture\Http\RequestFilter;
use HttpCapture\Http\Response;
use HttpCapture\Http\Router;
use HttpCapture\Persistence\DatabaseConnection;
use HttpCapture\Persistence\RequestRepository;

final class Application
{
    private Router $router;
    private RequestsController $requestsController;
    private CaptureController $captureController;
    private RequestFilter $requestFilter;

    public function __construct(?string $storagePath = null, ?RequestFilter $requestFilter = null)
    {
        $storagePath = $storagePath ?? dirname(__DIR__) . '/storage/httpcapture.sqlite';
        $connection = new DatabaseConnection($storagePath);
        $repository = new RequestRepository($connection);

        $this->router = new Router();
        $this->requestsController = new RequestsController($repository, new MarkdownFormatter());
        $this->captureController = new CaptureController($repository);
        $this->requestFilter = $requestFilter ?? RequestFilter::default();

        $this->registerRoutes();
    }

    public function handle(array $server, string $rawBody, array $queryParams = [], array $parsedBody = [], array $files = []): Response
    {
        $request = Request::fromGlobals($server, $rawBody, $queryParams, $parsedBody, $files);

        $matched = $this->router->dispatch($request);
        if ($matched instanceof Response) {
            return $matched;
        }

        if ($this->isReservedApiPath($request)) {
            return $this->apiNotFoundResponse($request);
        }

        if ($request->getMethod() === 'GET' && $this->shouldRenderUi($request)) {
            return $this->renderUi();
        }

        if (!$this->requestFilter->shouldCapture($request)) {
            return Response::empty();
        }

        if ($request->getMethod() === 'GET') {
            return $this->captureController->store($request, 200, 'OK', false);
        }

        return $this->captureController->store($request);
    }

    private function registerRoutes(): void
    {
        $this->router->add('GET', '/api/requests', fn (Request $request): Response => $this->requestsController->index($request));
        $this->router->add('GET', '/api/requests/{id}', fn (Request $request, array $params): Response => $this->requestsController->show($request, (int) $params['id']));
        $this->router->add('DELETE', '/api/requests/{id}', fn (Request $request, array $params): Response => $this->requestsController->destroy($request, (int) $params['id']));
        $this->router->add('DELETE', '/api/requests', fn (Request $request): Response => $this->requestsController->destroyAll($request));
    }

    private function shouldRenderUi(Request $request): bool
    {
        $path = $request->getPath();

        if ($path === '/' || $path === '/index.php') {
            return true;
        }

        $accept = $request->getHeader('Accept');
        if (is_string($accept) && str_contains($accept, 'text/html')) {
            return true;
        }

        return false;
    }

    private function renderUi(): Response
    {
        $body = file_get_contents(__DIR__ . '/../public/index.html');

        return Response::html($body ?: '<h1>httpcapture</h1>');
    }

    private function isReservedApiPath(Request $request): bool
    {
        $path = $request->getPath();

        return $path === '/api' || str_starts_with($path, '/api/');
    }

    private function apiNotFoundResponse(Request $request): Response
    {
        if ($this->wantsMarkdown($request)) {
            return Response::markdown("Route not found.\n", 404);
        }

        return Response::json(['message' => 'Route not found'], 404);
    }

    private function wantsMarkdown(Request $request): bool
    {
        $accept = $request->getHeader('Accept');

        return is_string($accept) && str_contains($accept, 'text/markdown');
    }
}
