<?php

declare(strict_types=1);

namespace App;

/**
 * Synchroniser-token CSRF protection: one random token per session, sent back by
 * forms (hidden field) and by fetch() calls (X-CSRF-Token header).
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf';
    private const TOKEN_BYTES = 32;

    /** @param array<string, mixed> $session the session array, passed by reference */
    public function __construct(private array &$session)
    {
    }

    public function token(): string
    {
        if (!isset($this->session[self::SESSION_KEY])) {
            $this->session[self::SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_BYTES));
        }
        return $this->session[self::SESSION_KEY];
    }

    public function isValid(?string $submitted): bool
    {
        $expected = $this->session[self::SESSION_KEY] ?? null;
        // hash_equals compares in constant time, so response timing leaks nothing about the token.
        return is_string($expected) && is_string($submitted) && hash_equals($expected, $submitted);
    }
}
