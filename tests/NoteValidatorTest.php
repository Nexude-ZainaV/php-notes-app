<?php

declare(strict_types=1);

namespace Tests;

use App\NoteValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoteValidatorTest extends TestCase
{
    public function testTrimsAndAcceptsValidInput(): void
    {
        [$data, $errors] = NoteValidator::validate(['title' => '  Hi  ', 'body' => ' text ', 'is_pinned' => '1']);

        self::assertSame([], $errors);
        self::assertSame(['title' => 'Hi', 'body' => 'text', 'is_pinned' => true], $data);
    }

    /** @return iterable<string, array{array<mixed>, string}> */
    public static function invalidInput(): iterable
    {
        yield 'missing title' => [[], 'title'];
        yield 'blank title' => [['title' => '   '], 'title'];
        yield 'title not a string' => [['title' => ['array']], 'title'];
        yield 'title too long' => [['title' => str_repeat('a', NoteValidator::TITLE_MAX_LENGTH + 1)], 'title'];
        yield 'body too long' => [['title' => 'ok', 'body' => str_repeat('a', NoteValidator::BODY_MAX_LENGTH + 1)], 'body'];
    }

    /** @param array<mixed> $input */
    #[DataProvider('invalidInput')]
    public function testRejectsInvalidInput(array $input, string $field): void
    {
        [, $errors] = NoteValidator::validate($input);
        self::assertArrayHasKey($field, $errors);
    }

    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        // 120 multi-byte characters is 360 bytes but still a valid title.
        [, $errors] = NoteValidator::validate(['title' => str_repeat('é', NoteValidator::TITLE_MAX_LENGTH)]);
        self::assertSame([], $errors);
    }
}
