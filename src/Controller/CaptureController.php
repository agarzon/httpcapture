<?php

declare(strict_types=1);

namespace HttpCapture\Controller;

use HttpCapture\Http\Request;
use HttpCapture\Http\Response;
use HttpCapture\Persistence\RequestRepository;

final class CaptureController
{
    public function __construct(private readonly RequestRepository $repository)
    {
    }

    public function store(Request $request): Response
    {
        $server = $request->getServerParams();
        $scheme = $this->detectScheme($server);
        $host = $server['HTTP_HOST'] ?? ($server['SERVER_NAME'] ?? 'localhost');
        $fullUrl = sprintf('%s://%s%s', $scheme, $host, $request->getUri());

        $record = $this->repository->store([
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'full_url' => $fullUrl,
            'query_params' => $request->getQueryParams(),
            'headers' => $request->getHeaders(),
            'body' => $request->getBody(),
            'client_ip' => $request->getClientIp(),
        ]);

        return Response::json([
            'message' => 'Request captured',
            'data' => $record,
        ], 201);
    }

    /**
     * @param array<string, mixed> $server
     */
    private function detectScheme(array $server): string
    {
        $https = $server['HTTPS'] ?? '';

        if (is_string($https) && in_array(strtolower($https), ['on', '1', 'true'], true)) {
            return 'https';
        }

        $forwardedProto = $server['HTTP_X_FORWARDED_PROTO'] ?? null;
        if (is_string($forwardedProto) && $forwardedProto !== '') {
            return strtolower($forwardedProto);
        }

        return 'http';
    }
}
