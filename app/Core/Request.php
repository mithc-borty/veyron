<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;

/**
 * The incoming HTTP request: method, path, query, headers and JSON body.
 *
 * The class is read-only apart from the route parameters that the Router
 * fills in once a route has matched.
 */
final class Request
{
    /**
     * Route parameters extracted from the matched route (e.g. id).
     *
     * @var array<string, string>
     */
    private array $routeParams = [];

    /**
     * Cache for the decoded JSON body.
     *
     * @var array<string, mixed>|null
     */
    private ?array $body = null;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers Header names are lower case.
     * @param array<string, string> $cookies Cookie name => value.
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $headers,
        private string $rawBody,
        private array $cookies = []
    ) {
    }

    /**
     * Build the request from the PHP superglobals.
     */
    public static function capture(): self
    {
        return new self(
            self::resolveMethod(),
            self::resolvePath(),
            $_GET,
            self::resolveHeaders(),
            self::resolveRawBody(),
            self::resolveCookies()
        );
    }

    private static function resolveMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Normalised request path: always starts with "/", never ends with "/".
     */
    private static function resolvePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        return '/' . trim(rawurldecode($path), '/');
    }

    /**
     * @return array<string, string>
     */
    private static function resolveHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $name => $value) {
                $headers[strtolower((string) $name)] = (string) $value;
            }
        }

        // Fallback / completion from $_SERVER (HTTP_* variables).
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value) || !str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] ??= $value;
        }

        // PHP does not expose these two with the HTTP_ prefix.
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] ??= (string) $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] ??= (string) $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    private static function resolveRawBody(): string
    {
        $body = file_get_contents('php://input');

        return $body === false ? '' : $body;
    }

    /**
     * @return array<string, string>
     */
    private static function resolveCookies(): array
    {
        $cookies = [];

        foreach ($_COOKIE as $name => $value) {
            $cookies[(string) $name] = (string) $value;
        }

        return $cookies;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParams(): array
    {
        return $this->query;
    }

    /**
     * Read a query string value, e.g. ?page=2
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Read a header value; the name is case-insensitive.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Decoded JSON request body (empty array when there is no body).
     *
     * @return array<string, mixed>
     * @throws JsonException When the body is not valid JSON.
     */
    public function body(): array
    {
        if ($this->body !== null) {
            return $this->body;
        }

        $this->body = [];

        if (trim($this->rawBody) === '') {
            return $this->body;
        }

        $decoded = json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new JsonException('JSON request body must be an object.');
        }

        $this->body = $decoded;

        return $this->body;
    }

    /**
     * Read a single field from the JSON body.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body()[$key] ?? $default;
    }

    /**
     * Whether the JSON body contains the given field.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body());
    }

    /**
     * Read a single field from a form-encoded (application/x-www-form-urlencoded)
     * submission, i.e. $_POST. Used by web routes; the JSON API uses
     * body()/input() instead.
     */
    public function form(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Validate the decoded JSON body.
     *
     * Thin shortcut for controllers: the rule engine itself lives in
     * App\Core\Validator.
     *
     *     $errors = $request->validate([
     *         'name'  => 'required|string|min:2|max:80',
     *         'email' => 'required|email|unique:users,email',
     *     ]);
     *
     * @param array<string, string> $rules
     * @return array<string, string> field => first error message ([] when valid)
     */
    public function validate(array $rules): array
    {
        return Validator::validate($this->body(), $rules);
    }

    /**
     * Called by the Router after a route matched.
     *
     * @param array<string, string> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * @return array<string, string>
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Read a route parameter, e.g. {id} in /users/{id}.
     */
    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }
}