<?php

declare(strict_types=1);

/**
 * Global helper functions.
 *
 * Kept intentionally small: only helpers that are used by more than one class
 * belong here.
 */

use App\Config\App;

if (!function_exists('base_path')) {
    /**
     * Absolute path inside the project root.
     *
     *   base_path('storage/logs')
     */
    function base_path(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $base = str_replace('\\', '/', $base);

        return $path === '' ? $base : $base . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Absolute path inside storage/.
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path === '' ? '' : '/' . $path));
    }
}

if (!function_exists('env')) {
    /**
     * Read a value from the environment (.env file or a real environment variable).
     */
    function env(string $key, ?string $default = null): ?string
    {
        return App::env($key, $default);
    }
}

if (!function_exists('app_debug')) {
    /**
     * Whether the application runs with APP_DEBUG=true.
     */
    function app_debug(): bool
    {
        return App::isDebug();
    }
}

if (!function_exists('url')) {
    /**
     * Prefix a path with the application base path so links and redirects work
     * both at a domain root (javascript.local) and in a subdirectory
     * deployment (localhost/javascript).
     *
     *   url('/login')  =>  "/login"  or  "/javascript/login"
     */
    function url(string $path = '/'): string
    {
        $base = defined('WEB_BASE') ? WEB_BASE : '';

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('view')) {
    /**
     * Render a view template (App\Core\View).
     *
     *   view('home', ['title' => 'Welcome'], 'layouts.app')
     */
    function view(string $view, array $data = [], ?string $layout = null): string
    {
        return \App\Core\View::render($view, $data, $layout);
    }
}

if (!function_exists('e')) {
    /**
     * HTML-escape a value for safe output inside templates.
     */
    function e(mixed $value): string
    {
        return \App\Core\View::escape($value);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * CSRF token of the current session (starts the session when needed).
     */
    function csrf_token(): string
    {
        return \App\Core\Session::csrfToken();
    }
}

if (!function_exists('session_has_user')) {
    /**
     * Whether the current session belongs to a logged-in user. Templates use
     * this instead of the namespaced App\Core\Session class.
     */
    function session_has_user(): bool
    {
        return \App\Core\Session::hasUser();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Hidden form input carrying the CSRF token. Include it in every web form.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}