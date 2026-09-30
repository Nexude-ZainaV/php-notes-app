<?php

declare(strict_types=1);

namespace App;

use PDO;

/** Composition root: wires the dependencies and the routes in one place. */
final class App
{
    public const SECURITY_HEADERS = [
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; "
            . "img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    private readonly Router $router;

    /** @param array<string, mixed> $session the session store, by reference ($_SESSION in production) */
    public function __construct(PDO $pdo, array &$session, string $templateDir)
    {
        Database::migrate($pdo);
        $notes = new NoteController(new NoteRepository($pdo), new Csrf($session), new View($templateDir));

        $this->router = (new Router())
            ->add('GET', '/', $notes->home(...))
            ->add('POST', '/notes', $notes->createFromForm(...))
            ->add('GET', '/api/notes', $notes->list(...))
            ->add('POST', '/api/notes', $notes->create(...))
            ->add('GET', '/api/notes/{id}', $notes->show(...))
            ->add('PUT', '/api/notes/{id}', $notes->update(...))
            ->add('DELETE', '/api/notes/{id}', $notes->delete(...));
    }

    public function handle(Request $request): Response
    {
        $response = $this->router->dispatch($request);
        return new Response($response->status, $response->body, $response->headers + self::SECURITY_HEADERS);
    }
}
