<?php

declare(strict_types=1);

namespace Tests;

use App\Database;
use App\NoteRepository;
use PHPUnit\Framework\TestCase;

final class NoteRepositoryTest extends TestCase
{
    private NoteRepository $notes;

    protected function setUp(): void
    {
        $pdo = Database::connect('sqlite::memory:');
        Database::migrate($pdo);
        $this->notes = new NoteRepository($pdo);
    }

    public function testCreateAndFind(): void
    {
        $created = $this->notes->create('Title', 'Body', true);

        self::assertSame($created, $this->notes->find($created['id']));
        self::assertTrue($created['is_pinned']);
        self::assertNull($this->notes->find(999));
    }

    public function testPinnedNotesComeFirstThenNewest(): void
    {
        $this->notes->create('old', '');
        $this->notes->create('pinned', '', true);
        $this->notes->create('new', '');

        self::assertSame(['pinned', 'new', 'old'], array_column($this->notes->all(), 'title'));
    }

    public function testSearchMatchesTitleOrBody(): void
    {
        $this->notes->create('Shopping', 'eggs and MILK');
        $this->notes->create('Milk run', '');
        $this->notes->create('Other', '');

        self::assertCount(2, $this->notes->all('milk'));
    }

    public function testSearchTreatsLikeWildcardsAsText(): void
    {
        $this->notes->create('100% done', '');
        $this->notes->create('1000 things', '');
        $this->notes->create('snake_case', '');
        $this->notes->create('snakeXcase', '');

        self::assertSame(['100% done'], array_column($this->notes->all('100%'), 'title'));
        self::assertSame(['snake_case'], array_column($this->notes->all('e_c'), 'title'));
    }

    public function testSqlInjectionAttemptIsJustText(): void
    {
        $this->notes->create("Robert'); DROP TABLE notes;--", '');

        self::assertCount(1, $this->notes->all("'); DROP"));
        self::assertCount(1, $this->notes->all());
    }

    public function testUpdateAndDeleteReportMissingRows(): void
    {
        self::assertNull($this->notes->update(42, 't', 'b', false));
        self::assertFalse($this->notes->delete(42));

        $id = $this->notes->create('a', '')['id'];
        self::assertSame('b', $this->notes->update($id, 'b', '', false)['title']);
        self::assertTrue($this->notes->delete($id));
    }
}
