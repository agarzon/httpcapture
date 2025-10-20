<?php

declare(strict_types=1);

namespace HttpCapture;

use HttpCapture\Controller\CaptureController;
use HttpCapture\Controller\RequestsController;
use HttpCapture\Http\Request;
use HttpCapture\Http\Response;
use HttpCapture\Http\Router;
use HttpCapture\Persistence\DatabaseConnection;
use HttpCapture\Persistence\RequestRepository;

final class Application
{
    private Router $router;
    private RequestsController $requestsController;
    private CaptureController $captureController;

    public function __construct(?string $storagePath = null)
    {
        $storagePath = $storagePath ?? dirname(__DIR__) . '/storage/httpcapture.sqlite';
        $connection = new DatabaseConnection($storagePath);
        $repository = new RequestRepository($connection);

        $this->router = new Router();
        $this->requestsController = new RequestsController($repository);
        $this->captureController = new CaptureController($repository);

        $this->registerRoutes();
    }

    public function handle(array $server, string $rawBody, array $queryParams = [], array $parsedBody = [], array $files = []): Response
    {
        $request = Request::fromGlobals($server, $rawBody, $queryParams, $parsedBody, $files);

        $matched = $this->router->dispatch($request);
        if ($matched instanceof Response) {
            return $matched;
        }

        if (str_starts_with($request->getPath(), '/api')) {
            return Response::json(['message' => 'Route not found'], 404);
        }

        if ($request->getMethod() === 'GET' && $this->shouldRenderUi($request)) {
            return $this->renderUi();
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
}
