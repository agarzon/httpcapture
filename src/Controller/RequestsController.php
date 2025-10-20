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
        $items = $this->repository->all();

        return Response::json([
            'data' => $items,
            'meta' => [
                'count' => count($items),
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
