<?php

declare(strict_types=1);

namespace Tests;

use App\App;
use App\Csrf;
use App\Database;
use App\Request;
use App\Response;
use PHPUnit\Framework\TestCase;

/** End-to-end: requests go through the real router, controller, templates and an in-memory database. */
final class AppTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $session = [];
    private App $app;
    private string $token;

    protected function setUp(): void
    {
        $this->app = new App(Database::connect('sqlite::memory:'), $this->session, __DIR__ . '/../templates');
        $this->token = (new Csrf($this->session))->token();
    }

    /** @param array<mixed>|null $json */
    private function send(string $method, string $path, ?array $json = null, ?string $token = null, array $query = []): Response
    {
        $headers = ['x-csrf-token' => $token ?? $this->token, 'content-type' => 'application/json'];
        $body = $json === null ? '' : json_encode($json, JSON_THROW_ON_ERROR);
        return $this->app->handle(new Request($method, $path, $query, [], $headers, $body));
    }

    /** @return array<mixed> */
    private static function decode(Response $response): array
    {
        return json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testCreateReturns201WithLocation(): void
    {
        $response = $this->send('POST', '/api/notes', ['title' => 'Groceries', 'body' => 'milk']);

        self::assertSame(201, $response->status);
        $note = self::decode($response)['note'];
        self::assertSame('/api/notes/' . $note['id'], $response->headers['Location']);
        self::assertSame('Groceries', $note['title']);
    }

    public function testWritesWithoutValidCsrfTokenAreForbidden(): void
    {
        self::assertSame(403, $this->send('POST', '/api/notes', ['title' => 'x'], token: 'forged')->status);
        self::assertSame(403, $this->send('DELETE', '/api/notes/1', token: '')->status);
    }

    public function testValidationErrorsReturn422WithFieldMessages(): void
    {
        $response = $this->send('POST', '/api/notes', ['title' => '   ']);

        self::assertSame(422, $response->status);
        self::assertArrayHasKey('title', self::decode($response)['errors']);
    }

    public function testNonJsonBodyIs400(): void
    {
        $request = new Request('POST', '/api/notes', [], [], ['x-csrf-token' => $this->token], 'not json');
        self::assertSame(400, $this->app->handle($request)->status);
    }

    public function testUpdateAndDeleteLifecycle(): void
    {
        $id = self::decode($this->send('POST', '/api/notes', ['title' => 'Draft']))['note']['id'];

        $updated = $this->send('PUT', "/api/notes/$id", ['title' => 'Final', 'body' => 'done', 'is_pinned' => true]);
        self::assertSame(200, $updated->status);
        self::assertTrue(self::decode($updated)['note']['is_pinned']);

        self::assertSame(204, $this->send('DELETE', "/api/notes/$id")->status);
        self::assertSame(404, $this->send('GET', "/api/notes/$id")->status);
        self::assertSame(404, $this->send('PUT', "/api/notes/$id", ['title' => 'Gone'])->status);
    }

    public function testListSearchesNotes(): void
    {
        $this->send('POST', '/api/notes', ['title' => 'Buy milk']);
        $this->send('POST', '/api/notes', ['title' => 'Call mom']);

        $notes = self::decode($this->send('GET', '/api/notes', query: ['q' => 'milk']))['notes'];

        self::assertSame(['Buy milk'], array_column($notes, 'title'));
    }

    public function testHomePageEscapesUserContent(): void
    {
        $this->send('POST', '/api/notes', ['title' => '<script>alert(1)</script>']);

        $page = $this->send('GET', '/');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page->body);
        self::assertStringNotContainsString('<script>alert(1)', $page->body);
    }

    public function testFormFallbackRedirectsAfterPost(): void
    {
        $form = ['_csrf' => $this->token, 'title' => 'From a form', 'body' => ''];
        $response = $this->app->handle(new Request('POST', '/notes', [], $form));

        self::assertSame(303, $response->status);
        self::assertSame('/', $response->headers['Location']);
    }

    public function testEveryResponseCarriesSecurityHeaders(): void
    {
        foreach ([$this->send('GET', '/'), $this->send('GET', '/api/notes'), $this->send('GET', '/missing')] as $response) {
            self::assertArrayHasKey('Content-Security-Policy', $response->headers);
            self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        }
    }

    public function testUnknownRouteIs404AndWrongMethodIs405(): void
    {
        self::assertSame(404, $this->send('GET', '/api/nothing')->status);
        self::assertSame(404, $this->send('GET', '/api/notes/abc')->status);   // ids are digits only

        $response = $this->send('PATCH', '/api/notes/1');
        self::assertSame(405, $response->status);
        self::assertSame('GET, PUT, DELETE', $response->headers['Allow']);
    }
}
