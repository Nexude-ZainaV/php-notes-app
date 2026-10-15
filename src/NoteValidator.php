<?php

declare(strict_types=1);

namespace App;

/** Input rules for a note, shared by the HTML form and the JSON API. */
final class NoteValidator
{
    public const TITLE_MAX_LENGTH = 120;
    public const BODY_MAX_LENGTH = 10_000;

    /**
     * Validates untrusted input (a decoded JSON body or $_POST).
     *
     * @param array<mixed> $input
     * @return array{0: array{title: string, body: string, is_pinned: bool}, 1: array<string, string>}
     *         the cleaned values and the errors keyed by field (empty when valid)
     */
    public static function validate(array $input): array
    {
        $title = is_string($input['title'] ?? null) ? trim($input['title']) : '';
        $body = is_string($input['body'] ?? null) ? trim($input['body']) : '';
        // '1', 'true', 'on' and JSON true count as pinned; anything else is false.
        $isPinned = filter_var($input['is_pinned'] ?? false, FILTER_VALIDATE_BOOL);

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            $errors['title'] = 'Title must be at most ' . self::TITLE_MAX_LENGTH . ' characters.';
        }
        if (mb_strlen($body) > self::BODY_MAX_LENGTH) {
            $errors['body'] = 'Note must be at most ' . self::BODY_MAX_LENGTH . ' characters.';
        }

        return [['title' => $title, 'body' => $body, 'is_pinned' => $isPinned], $errors];
    }
}
