# Notes App (PHP 8.2, no framework)

[![CI](https://github.com/Nexude-ZainaV/php-notes-app/actions/workflows/ci.yml/badge.svg)](https://github.com/Nexude-ZainaV/php-notes-app/actions/workflows/ci.yml)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![PHPUnit](https://img.shields.io/badge/tests-PHPUnit%2011-366488)
![License: MIT](https://img.shields.io/badge/license-MIT-green)

A notes app in plain modern PHP. I wrote it without a framework on purpose, to build
the parts a framework normally hides: a front controller and router, PDO with prepared
statements, CSRF protection, output escaping, security headers, and a JSON API that the
page calls with `fetch()`. The page is rendered on the server and works without
JavaScript. With JavaScript on, it gets live search and in-place create, edit, pin and
delete.

## Features

- Create, edit, pin and delete notes without page reloads (AJAX via `fetch`).
- Live search with a debounce. The query stays in the URL, so results can be shared.
- Without JavaScript, the create form falls back to a normal POST and redirect.
- The layout is a single column on phones and two columns from 900px. The card grid
  fits as many 16rem columns as the screen allows, and dark mode follows the OS.
- Fields are labelled, errors use `aria-invalid` and `role="alert"`, status messages go
  to a live region, focus rings are visible and touch targets are 44px.

## Security

| Threat | Mitigation | Where |
|---|---|---|
| SQL injection | Every value is bound with PDO prepared statements; emulated prepares are off | [src/NoteRepository.php](src/NoteRepository.php) |
| Cross-site scripting | `htmlspecialchars` on all template output; the JS uses `textContent`, never `innerHTML`; a strict Content-Security-Policy | [src/View.php](src/View.php), [public/assets/app.js](public/assets/app.js), [src/App.php](src/App.php) |
| Cross-site request forgery | A random token per session, checked with `hash_equals` on every write (form field or `X-CSRF-Token` header); `SameSite=Lax` cookie | [src/Csrf.php](src/Csrf.php) |
| Session theft or fixation | `HttpOnly`, `SameSite`, `Secure` on HTTPS, `use_strict_mode` | [public/index.php](public/index.php) |
| Information leaks | Errors go to the log; users see a generic 500 | [public/index.php](public/index.php) |
| Bad input | Type checks, trimming, length limits counted in characters (`mb_strlen`), escaped `LIKE` wildcards | [src/NoteValidator.php](src/NoteValidator.php) |

## JSON API

Every write needs the `X-CSRF-Token` header. The page puts the token in a `<meta>` tag.

| Method | Route | Success | Errors |
|---|---|---|---|
| `GET` | `/api/notes?q=` | `200 {"notes": [...]}` | |
| `GET` | `/api/notes/{id}` | `200 {"note": {...}}` | `404` |
| `POST` | `/api/notes` | `201` + `Location` | `400` bad JSON, `403` CSRF, `422 {"errors": {...}}` |
| `PUT` | `/api/notes/{id}` | `200 {"note": {...}}` | `403`, `404`, `422` |
| `DELETE` | `/api/notes/{id}` | `204` | `403`, `404` |

A known route with the wrong method returns `405` with an `Allow` header.

## Layout

```
public/index.php        front controller: session, error boundary, send the response
src/App.php             composition root: dependencies, routes, security headers
src/Router.php          regex routes with {id} params, 404 / 405 handling
src/NoteController.php  request -> response; no SQL, no HTML
src/NoteRepository.php  all SQL, prepared statements
src/NoteValidator.php   input rules shared by the form and the API
src/Csrf.php            synchroniser token
src/Request.php         request value object (testable without superglobals)
src/Response.php        response value object (testable without output)
src/View.php            template rendering + escaping
templates/home.php      the page
public/assets/          app.js (AJAX), app.css (responsive layout)
```

`Request` and `Response` are plain value objects. That lets the tests run the whole
app (router, controller, templates, database) without a web server.

## Run it

You need PHP 8.2+ with `pdo_sqlite`, and [Composer](https://getcomposer.org).

```bash
composer install
composer serve          # php -S localhost:8080 -t public
```

The database file is created at `var/notes.sqlite`. Set `NOTES_DB_DSN` to put it
somewhere else. In production, point the web server's document root at `public/` so
nothing else can be reached.

Tests:

```bash
composer test
```

## License

[MIT](LICENSE)
