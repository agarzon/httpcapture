<?php

declare(strict_types=1);

namespace HttpCapture\Controller;

use HttpCapture\Http\Request;
use HttpCapture\Http\Response;
use HttpCapture\Persistence\RequestRepository;

final class RequestsController
{
    public function __construct(private readonly RequestRepository $repository)
    {
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

        return Response::json([
            'data' => $items,
            'meta' => [
                'count' => count($items),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $record = $this->repository->find($id);

        if ($record === null) {
            return Response::json(['message' => 'Request not found'], 404);
        }

        return Response::json(['data' => $record]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $record = $this->repository->find($id);

        if ($record === null) {
            return Response::json(['message' => 'Request not found'], 404);
        }

        $this->repository->delete($id);

        return Response::json(['message' => 'Request deleted', 'data' => ['id' => $id]]);
    }

    public function destroyAll(Request $request): Response
    {
        $this->repository->deleteAll();

        return Response::json(['message' => 'All requests cleared']);
    }
}
