<?php

declare(strict_types=1);

namespace Tests;

use App\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenIsRandomStableAndStoredInSession(): void
    {
        $session = [];
        $csrf = new Csrf($session);

        $token = $csrf->token();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertSame($token, $csrf->token());
        self::assertSame($token, $session['_csrf']);
        $otherSession = [];
        self::assertNotSame($token, (new Csrf($otherSession))->token());
    }

    public function testValidation(): void
    {
        $session = [];
        $csrf = new Csrf($session);
        self::assertFalse($csrf->isValid('anything'), 'no token issued yet');

        $token = $csrf->token();
        self::assertTrue($csrf->isValid($token));
        self::assertFalse($csrf->isValid(null));
        self::assertFalse($csrf->isValid(''));
        self::assertFalse($csrf->isValid(strtoupper($token)));
    }
}
