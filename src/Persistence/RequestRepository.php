<?php

declare(strict_types=1);

namespace HttpCapture\Persistence;

use DateTimeImmutable;
use JsonException;
use PDO;

final class RequestRepository
{
    private PDO $pdo;

    public function __construct(DatabaseConnection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(int $limit = 200): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM requests ORDER BY id DESC LIMIT :limit;'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapRow'], $results ?: []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM requests WHERE id = :id;');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @param array{
     *     method: string,
     *     path: string,
     *     full_url: string,
     *     query_params: array<string, mixed>,
     *     headers: array<string, string>,
     *     body: string,
     *     client_ip: string
     * } $request
     *
     * @return array<string, mixed>
     */
    public function store(array $request): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO requests (method, path, full_url, query_params, headers, body, client_ip, created_at)
             VALUES (:method, :path, :full_url, :query_params, :headers, :body, :client_ip, :created_at)'
        );

        $createdAt = (new DateTimeImmutable())->format(DateTimeImmutable::ATOM);

        $statement->execute([
            ':method' => $request['method'],
            ':path' => $request['path'],
            ':full_url' => $request['full_url'],
            ':query_params' => json_encode($request['query_params'], JSON_THROW_ON_ERROR),
            ':headers' => json_encode($request['headers'], JSON_THROW_ON_ERROR),
            ':body' => $request['body'],
            ':client_ip' => $request['client_ip'],
            ':created_at' => $createdAt,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return $this->find($id) ?? [];
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM requests WHERE id = :id;');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);

        return $statement->execute();
    }

    public function deleteAll(): void
    {
        $this->pdo->exec('DELETE FROM requests;');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        $row['query_params'] = $this->safeDecode($row['query_params'] ?? '{}');
        $row['headers'] = $this->safeDecode($row['headers'] ?? '{}');

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeDecode(string $payload): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
