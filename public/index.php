<?php

/**
 * Front controller: every request that is not a static file lands here.
 * Local dev:  php -S localhost:8080 -t public   (or: composer serve)
 */

declare(strict_types=1);

use App\App;
use App\Database;
use App\Request;
use App\Response;

// PHP's built-in server: let it serve real files in public/ (CSS, JS) directly.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../vendor/autoload.php';

session_start([
    'cookie_httponly' => true,                            // JavaScript cannot read the session cookie
    'cookie_samesite' => 'Lax',                           // not sent on cross-site POSTs
    'cookie_secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
    'use_strict_mode' => true,                            // reject session ids the server did not issue
]);

try {
    $dsn = getenv('NOTES_DB_DSN') ?: 'sqlite:' . __DIR__ . '/../var/notes.sqlite';
    $app = new App(Database::connect($dsn), $_SESSION, __DIR__ . '/../templates');
    $app->handle(Request::fromGlobals())->send();
} catch (Throwable $error) {
    error_log((string) $error);                           // full detail goes to the log, never to the user
    (new Response(500, 'Something went wrong.', App::SECURITY_HEADERS + ['Content-Type' => 'text/plain']))->send();
}
