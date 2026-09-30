<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    /** Opens a PDO connection configured for safety: exceptions on error, real prepared statements. */
    public static function connect(string $dsn): PDO
    {
        $pdo = new PDO($dsn, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    /** Idempotent schema setup; safe to call on every request for a single-file SQLite app. */
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS notes (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                title      TEXT    NOT NULL,
                body       TEXT    NOT NULL DEFAULT '',
                is_pinned  INTEGER NOT NULL DEFAULT 0 CHECK (is_pinned IN (0, 1)),
                created_at TEXT    NOT NULL,
                updated_at TEXT    NOT NULL
            );
            CREATE INDEX IF NOT EXISTS ix_notes_pinned_updated ON notes (is_pinned, updated_at);
            SQL);
    }
}
