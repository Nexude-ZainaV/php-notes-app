<?php

declare(strict_types=1);

namespace App;

/**
 * Minimal regex router. Patterns use {name} placeholders that match digits,
 * e.g. '/api/notes/{id}'; the handler is called as handler($request, id: 7).
 * Unknown paths give 404, known paths with the wrong method give 405 with an Allow header.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): self
    {
        // '/api/notes/{id}' becomes '#^/api/notes/(?P<id>\d+)$#'
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
        $this->routes[] = ['method' => strtoupper($method), 'regex' => $regex, 'handler' => $handler];
        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path;
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== strtoupper($request->method)) {
                $allowed[] = $route['method'];
                continue;
            }
            // Named groups become named arguments: {id} -> id: (int)
            $params = array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            return ($route['handler'])($request, ...$params);
        }

        if ($allowed !== []) {
            return new Response(405, 'Method Not Allowed', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        return str_starts_with($path, '/api/')
            ? Response::json(['error' => 'Not found'], 404)
            : Response::html('<h1>404 Not Found</h1>', 404);
    }
}
