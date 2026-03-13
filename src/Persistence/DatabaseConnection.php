<?php

declare(strict_types=1);

namespace HttpCapture\Persistence;

use PDO;
use PDOException;

final class DatabaseConnection
{
    private PDO $pdo;

    public function __construct(string $databasePath)
    {
        $directory = dirname($databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new PDOException(sprintf('Unable to create storage directory: %s', $directory));
        }

        $this->pdo = new PDO('sqlite:' . $databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');

        $this->initialise();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function initialise(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                method TEXT NOT NULL,
                path TEXT NOT NULL,
                full_url TEXT NOT NULL,
                query_params TEXT NOT NULL,
                headers TEXT NOT NULL,
                body TEXT,
                form_data TEXT,
                files TEXT,
                client_ip TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        SQL);

        $this->ensureMigrations();
    }

    private function ensureMigrations(): void
    {
        $statement = $this->pdo->query('PRAGMA table_info(requests)');
        $columns = $statement ? $statement->fetchAll(PDO::FETCH_COLUMN, 1) : [];

        $migrations = [
            'form_data' => 'TEXT',
            'files' => 'TEXT',
        ];

        foreach ($migrations as $column => $definition) {
            if (!in_array($column, $columns, true)) {
                $this->pdo->exec(sprintf('ALTER TABLE requests ADD COLUMN %s %s', $column, $definition));
            }
        }
    }
}
