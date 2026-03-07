<?php

declare(strict_types=1);

namespace HttpCapture\Controller;

use HttpCapture\Formatter\MarkdownFormatter;
use HttpCapture\Http\Request;
use HttpCapture\Http\Response;
use HttpCapture\Persistence\RequestRepository;

final class RequestsController
{
    public function __construct(
        private readonly RequestRepository $repository,
        private readonly MarkdownFormatter $markdownFormatter,
    ) {
    }

    public function index(Request $request): Response
    {
        $query = $request->getQueryParams();
        $page = isset($query['page']) ? max(1, (int) $query['page']) : 1;
        $perPage = isset($query['per_page']) ? max(1, min((int) $query['per_page'], 100)) : 10;
        $offset = ($page - 1) * $perPage;

        $total = $this->repository->count();
        $items = $this->repository->all($perPage, $offset);
        $lastPage = (int) max(1, (int) ceil($total / $perPage));

        $meta = [
            'count' => count($items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];

        if ($this->wantsMarkdown($request)) {
            return Response::markdown($this->markdownFormatter->formatList($items, $meta));
        }

        return Response::json([
            'data' => $items,
            'meta' => $meta,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $record = $this->repository->find($id);

        if ($record === null) {
            return $this->notFoundResponse($request);
        }

        if ($this->wantsMarkdown($request)) {
            return Response::markdown($this->markdownFormatter->formatSingle($record));
        }

        return Response::json(['data' => $record]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $record = $this->repository->find($id);

        if ($record === null) {
            return $this->notFoundResponse($request);
        }

        $this->repository->delete($id);

        if ($this->wantsMarkdown($request)) {
            return Response::markdown("Request #{$id} deleted.\n");
        }

        return Response::json(['message' => 'Request deleted', 'data' => ['id' => $id]]);
    }

    public function destroyAll(Request $request): Response
    {
        $this->repository->deleteAll();

        if ($this->wantsMarkdown($request)) {
            return Response::markdown("All requests cleared.\n");
        }

        return Response::json(['message' => 'All requests cleared']);
    }

    private function wantsMarkdown(Request $request): bool
    {
        $accept = $request->getHeader('Accept');

        return is_string($accept) && str_contains($accept, 'text/markdown');
    }

    private function notFoundResponse(Request $request): Response
    {
        if ($this->wantsMarkdown($request)) {
            return Response::markdown("Request not found.\n", 404);
        }

        return Response::json(['message' => 'Request not found'], 404);
    }
}
