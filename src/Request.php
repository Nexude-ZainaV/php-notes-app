<?php

declare(strict_types=1);

namespace App;

/** The parts of an HTTP request the app reads, captured once as an immutable value. */
final class Request
{
    /**
     * @param array<string, mixed>  $query   $_GET
     * @param array<string, mixed>  $form    $_POST
     * @param array<string, string> $headers lower-cased header names
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $form = [],
        public readonly array $headers = [],
        public readonly string $rawBody = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        // PHP exposes headers as HTTP_* keys in $_SERVER; turn them back into lower-case names.
        // Content-Type is the odd one out: it arrives as CONTENT_TYPE, without the prefix.
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'),
            query: $_GET,
            form: $_POST,
            headers: $headers,
            rawBody: (string) file_get_contents('php://input'),
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function queryString(string $key): string
    {
        $value = $this->query[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    /** @return array<mixed>|null the decoded JSON object, or null if the body is not a JSON object */
    public function json(): ?array
    {
        try {
            $data = json_decode($this->rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($data) ? $data : null;
    }
}
