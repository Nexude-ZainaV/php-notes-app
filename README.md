# Notes App (PHP 8.2, no framework)

[![CI](https://github.com/Nexude-ZainaV/php-notes-app/actions/workflows/ci.yml/badge.svg)](https://github.com/Nexude-ZainaV/php-notes-app/actions/workflows/ci.yml)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![PHPUnit](https://img.shields.io/badge/tests-PHPUnit%2011-366488)
![License: MIT](https://img.shields.io/badge/license-MIT-green)

A notes app written in plain modern PHP to show the pieces a framework normally hides:
a front controller and router, PDO with prepared statements, CSRF protection, output
escaping, security headers, and an AJAX JSON API consumed with `fetch()`. The page is
server-rendered and works without JavaScript; the script upgrades it to live search
and in-place create, edit, pin and delete.

## Features

- Create, edit, pin and delete notes without page reloads (AJAX via `fetch`)
- Live search with debounce; the query is kept in the URL so results are shareable
- Works without JavaScript: the create form falls back to a normal POST + redirect
- Responsive: single column on phones, two columns from 900px, card grid that fits
  as many 16rem columns as the screen allows; dark mode follows the OS setting
- Accessible: labelled fields, `aria-invalid` + `role="alert"` errors, a live region
  for status messages, visible focus rings, 44px touch targets

## Security

| Threat | Mitigation | Where |
|---|---|---|
| SQL injection | Every value bound with PDO prepared statements; emulated prepares off | [src/NoteRepository.php](src/NoteRepository.php) |
| Cross-site scripting | `htmlspecialchars` on all output in templates; JS uses `textContent`, never `innerHTML`; strict Content-Security-Policy | [src/View.php](src/View.php), [public/assets/app.js](public/assets/app.js), [src/App.php](src/App.php) |
| Cross-site request forgery | Per-session random token, checked with `hash_equals` on every write (form field or `X-CSRF-Token` header); `SameSite=Lax` cookie | [src/Csrf.php](src/Csrf.php) |
| Session theft / fixation | `HttpOnly`, `SameSite`, `Secure` on HTTPS, `use_strict_mode` | [public/index.php](public/index.php) |
| Information leaks | Errors are logged; users get a generic 500 | [public/index.php](public/index.php) |
| Bad input | Type checks, trimming, length limits in characters (`mb_strlen`), `LIKE` wildcards escaped | [src/NoteValidator.php](src/NoteValidator.php) |

## JSON API

All write requests need the `X-CSRF-Token` header (the page exposes it in a `<meta>` tag).

| Method | Route | Success | Errors |
|---|---|---|---|
| `GET` | `/api/notes?q=` | `200 {"notes": [...]}` | |
| `GET` | `/api/notes/{id}` | `200 {"note": {...}}` | `404` |
| `POST` | `/api/notes` | `201` + `Location` | `400` bad JSON, `403` CSRF, `422 {"errors": {...}}` |
| `PUT` | `/api/notes/{id}` | `200 {"note": {...}}` | `403`, `404`, `422` |
| `DELETE` | `/api/notes/{id}` | `204` | `403`, `404` |

Wrong method on a known route returns `405` with an `Allow` header.

## Architecture

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

`Request` and `Response` are plain value objects, so the tests drive the whole app
(router, controller, templates, database) without a web server.

## Run it

Requires PHP 8.2+ with `pdo_sqlite`, and [Composer](https://getcomposer.org).

```bash
composer install
composer serve          # php -S localhost:8080 -t public
```

The database file is created at `var/notes.sqlite`; set `NOTES_DB_DSN` to use another
location. In production, point the web server's document root at `public/` so nothing
else is reachable.

Tests:

```bash
composer test
```

## License

[MIT](LICENSE)
