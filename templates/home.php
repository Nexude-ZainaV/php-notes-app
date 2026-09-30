<?php
/**
 * Home page. Server-rendered so it works without JavaScript; assets/app.js then
 * upgrades it to AJAX (create, edit, pin, delete and live search without reloads).
 *
 * @var list<array<string, mixed>> $notes
 * @var string                     $search
 * @var string                     $csrfToken
 * @var array<string, string>      $errors
 * @var array<string, mixed>       $old
 * @var int                        $titleMax
 * @var int                        $bodyMax
 * @var callable(mixed): string    $e  HTML escaper
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $e($csrfToken) ?>">
    <title>Notes</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
    <h1>Notes</h1>
    <form class="search" method="get" action="/" role="search">
        <label class="visually-hidden" for="q">Search notes</label>
        <input id="q" name="q" type="search" placeholder="Search notes" value="<?= $e($search) ?>" autocomplete="off">
    </form>
</header>

<main class="layout">
    <section class="composer" aria-labelledby="new-note-heading">
        <h2 id="new-note-heading">New note</h2>
        <form id="note-form" method="post" action="/notes" novalidate>
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

            <label for="title">Title</label>
            <input id="title" name="title" required maxlength="<?= $titleMax ?>"
                   value="<?= $e($old['title'] ?? '') ?>"
                   aria-describedby="title-error" <?= isset($errors['title']) ? 'aria-invalid="true"' : '' ?>>
            <p id="title-error" class="field-error" role="alert"><?= $e($errors['title'] ?? '') ?></p>

            <label for="body">Note</label>
            <textarea id="body" name="body" rows="5" maxlength="<?= $bodyMax ?>"
                      aria-describedby="body-error"><?= $e($old['body'] ?? '') ?></textarea>
            <p id="body-error" class="field-error" role="alert"><?= $e($errors['body'] ?? '') ?></p>

            <label class="checkbox">
                <input type="checkbox" name="is_pinned" value="1" <?= !empty($old['is_pinned']) ? 'checked' : '' ?>>
                Pin to top
            </label>

            <button type="submit">Save note</button>
        </form>
    </section>

    <section aria-labelledby="notes-heading">
        <h2 id="notes-heading" class="visually-hidden">Your notes</h2>
        <p id="status" class="status" role="status" aria-live="polite"></p>
        <ul id="notes" class="notes">
            <?php foreach ($notes as $note): ?>
                <li class="note<?= $note['is_pinned'] ? ' is-pinned' : '' ?>" data-id="<?= $note['id'] ?>">
                    <h3><?= $e($note['title']) ?></h3>
                    <p><?= nl2br($e($note['body'])) ?></p>
                    <time datetime="<?= $e($note['updated_at']) ?>"><?= $e(substr($note['updated_at'], 0, 10)) ?></time>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($notes === []): ?>
            <p class="empty"><?= $search === '' ? 'No notes yet. Write your first one.' : 'No notes match your search.' ?></p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
