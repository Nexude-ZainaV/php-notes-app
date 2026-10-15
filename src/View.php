<?php

declare(strict_types=1);

namespace App;

/** Renders the PHP templates in templates/ with an HTML escaper ($e) in scope. */
final class View
{
    public function __construct(private readonly string $templateDir)
    {
    }

    /** @param array<string, mixed> $vars variables made available to the template */
    public function render(string $template, array $vars = []): string
    {
        $file = $this->templateDir . '/' . $template . '.php';
        // A static closure gives the template its own scope: only $vars and $e are visible.
        $renderer = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            $e = View::escape(...);
            ob_start();
            require $__file;
            return (string) ob_get_clean();
        };
        return $renderer($file, $vars);
    }

    /** Escape for HTML text and attribute values. Every piece of user data goes through this. */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
