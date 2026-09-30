<?php

declare(strict_types=1);

namespace App;

/** Turns requests into responses. No SQL here (repository) and no HTML here (templates). */
final class NoteController
{
    public function __construct(
        private readonly NoteRepository $notes,
        private readonly Csrf $csrf,
        private readonly View $view,
    ) {
    }

    // ---- HTML pages ------------------------------------------------------------

    public function home(Request $request): Response
    {
        return $this->renderHome($request->queryString('q'));
    }

    /** No-JavaScript fallback for the create form: post/redirect/get. */
    public function createFromForm(Request $request): Response
    {
        if (!$this->csrf->isValid($request->form['_csrf'] ?? null)) {
            return Response::html('<h1>403 Forbidden</h1><p>Your session expired. Reload and try again.</p>', 403);
        }
        [$data, $errors] = NoteValidator::validate($request->form);
        if ($errors !== []) {
            return $this->renderHome('', $errors, $data, 422);
        }
        $this->notes->create($data['title'], $data['body'], $data['is_pinned']);
        return Response::redirect('/');
    }

    // ---- JSON API (used by assets/app.js) --------------------------------------

    public function list(Request $request): Response
    {
        return Response::json(['notes' => $this->notes->all($request->queryString('q'))]);
    }

    public function show(Request $request, int $id): Response
    {
        $note = $this->notes->find($id);
        return $note === null ? self::notFound() : Response::json(['note' => $note]);
    }

    public function create(Request $request): Response
    {
        return $this->withValidJson($request, function (array $data): Response {
            $note = $this->notes->create($data['title'], $data['body'], $data['is_pinned']);
            return new Response(201, json_encode(['note' => $note], JSON_THROW_ON_ERROR), [
                'Content-Type' => 'application/json; charset=utf-8',
                'Location' => '/api/notes/' . $note['id'],
            ]);
        });
    }

    public function update(Request $request, int $id): Response
    {
        return $this->withValidJson($request, function (array $data) use ($id): Response {
            $note = $this->notes->update($id, $data['title'], $data['body'], $data['is_pinned']);
            return $note === null ? self::notFound() : Response::json(['note' => $note]);
        });
    }

    public function delete(Request $request, int $id): Response
    {
        if (!$this->csrf->isValid($request->header('X-CSRF-Token'))) {
            return self::forbidden();
        }
        return $this->notes->delete($id) ? Response::noContent() : self::notFound();
    }

    // ---- helpers ------------------------------------------------------------------

    /** Shared guard for JSON writes: CSRF header, JSON body, then field validation. */
    private function withValidJson(Request $request, callable $onValid): Response
    {
        if (!$this->csrf->isValid($request->header('X-CSRF-Token'))) {
            return self::forbidden();
        }
        $input = $request->json();
        if ($input === null) {
            return Response::json(['error' => 'Request body must be a JSON object'], 400);
        }
        [$data, $errors] = NoteValidator::validate($input);
        if ($errors !== []) {
            return Response::json(['errors' => $errors], 422);
        }
        return $onValid($data);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old    values to refill the form with after a failed submit
     */
    private function renderHome(string $search, array $errors = [], array $old = [], int $status = 200): Response
    {
        return Response::html($this->view->render('home', [
            'notes' => $this->notes->all($search),
            'search' => $search,
            'csrfToken' => $this->csrf->token(),
            'errors' => $errors,
            'old' => $old,
            'titleMax' => NoteValidator::TITLE_MAX_LENGTH,
            'bodyMax' => NoteValidator::BODY_MAX_LENGTH,
        ]), $status);
    }

    private static function notFound(): Response
    {
        return Response::json(['error' => 'Note not found'], 404);
    }

    private static function forbidden(): Response
    {
        return Response::json(['error' => 'Invalid or missing CSRF token'], 403);
    }
}
