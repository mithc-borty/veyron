<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use Throwable;

/**
 * Minimal native PHP view renderer.
 *
 * Views are plain PHP files under resources/views/, addressed with dot
 * notation: "home" => resources/views/home.php, "layouts.app" =>
 * resources/views/layouts/app.php.
 *
 * Render with an optional layout. The template's output becomes $content
 * inside the layout:
 *
 *     Response::html(view('home', ['title' => 'Welcome'], 'layouts.app'));
 *
 * Inside templates always escape output with e() (App\Core\View::escape).
 * The only intentional exception is $content, which is trusted HTML produced
 * by another template of this application.
 */
final class View
{
    /**
     * Data shared with every template (e.g. app name).
     *
     * @var array<string, mixed>
     */
    private static array $shared = [];

    private function __construct()
    {
    }

    /**
     * Make data available to all subsequent renders.
     *
     * @param array<string, mixed> $data
     */
    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    /**
     * Render a template (optionally inside a layout) and return the HTML.
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException When the view does not exist.
     */
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = self::renderFile(self::resolve($view), $data + self::$shared);

        if ($layout === null) {
            return $content;
        }

        return self::renderFile(self::resolve($layout), ['content' => $content] + $data + self::$shared);
    }

    public static function exists(string $view): bool
    {
        try {
            return is_file(self::resolve($view));
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * HTML-escape any value for safe output (use through the e() helper).
     */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Map "layouts.app" to resources/views/layouts/app.php.
     *
     * Only [A-Za-z0-9_-] segments are accepted, so a crafted view name can
     * never escape the views directory.
     */
    private static function resolve(string $view): string
    {
        $segments = explode('.', $view);

        foreach ($segments as $segment) {
            if ($segment === '' || preg_match('/^[A-Za-z0-9_-]+$/', $segment) !== 1) {
                throw new InvalidArgumentException('Invalid view name: ' . $view);
            }
        }

        $path = base_path('resources/views/' . implode('/', $segments) . '.php');

        if (!is_file($path)) {
            throw new InvalidArgumentException('View not found: ' . $view);
        }

        return $path;
    }

    /**
     * Include a template in a clean variable scope and capture its output.
     *
     * @param array<string, mixed> $data
     */
    private static function renderFile(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();

        try {
            include $path;
        } catch (Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }
}
