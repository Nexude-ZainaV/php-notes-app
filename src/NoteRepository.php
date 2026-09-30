<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * All SQL for notes lives here. Every value is bound as a parameter, never
 * concatenated into the query, so user input cannot change the SQL.
 */
final class NoteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> pinned notes first, then most recently edited */
    public function all(string $search = ''): array
    {
        $sql = 'SELECT * FROM notes';
        $params = [];
        if ($search !== '') {
            $sql .= " WHERE title LIKE :q ESCAPE '\\' OR body LIKE :q ESCAPE '\\'";
            $params['q'] = '%' . addcslashes($search, '%_\\') . '%';
        }
        $sql .= ' ORDER BY is_pinned DESC, updated_at DESC, id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM notes WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : self::hydrate($row);
    }

    /** @return array<string, mixed> the stored note */
    public function create(string $title, string $body, bool $isPinned = false): array
    {
        $now = self::now();
        $statement = $this->pdo->prepare(
            'INSERT INTO notes (title, body, is_pinned, created_at, updated_at)
             VALUES (:title, :body, :is_pinned, :now, :now)'
        );
        $statement->execute(['title' => $title, 'body' => $body, 'is_pinned' => (int) $isPinned, 'now' => $now]);
        return $this->find((int) $this->pdo->lastInsertId());
    }

    /** @return array<string, mixed>|null the updated note, or null if it does not exist */
    public function update(int $id, string $title, string $body, bool $isPinned): ?array
    {
        $statement = $this->pdo->prepare(
            'UPDATE notes SET title = :title, body = :body, is_pinned = :is_pinned, updated_at = :now WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'is_pinned' => (int) $isPinned,
            'now' => self::now(),
        ]);
        return $statement->rowCount() === 0 ? null : $this->find($id);
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM notes WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->rowCount() > 0;
    }

    /** Microsecond precision keeps "most recently edited" ordering stable for quick edits. */
    private static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'body' => $row['body'],
            'is_pinned' => (bool) $row['is_pinned'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
}
