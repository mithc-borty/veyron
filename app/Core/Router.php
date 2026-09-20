<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Small router with GET/POST/PUT/PATCH/DELETE support and {param} placeholders.
 *
 * Route handlers receive the Request; route parameters are available through
 * $request->routeParam('id'). Handlers may be:
 *
 *   - a Closure:            static function (Request $request) { ... }
 *   - [Controller::class, 'method']
 *   - 'App\Controllers\X@method'
 *
 * There is deliberately no dependency-injection container.
 */
final class Router
{
    /**
     * @var array<int, array{method: string, regex: string, handler: mixed, middleware: array<int, mixed>}>
     */
    private array $routes = [];

    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
    }

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * Match the request against the registered routes and run the handler.
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $this->stripBasePath($request->path());
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $request->setRouteParams(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

            foreach ($route['middleware'] as $middleware) {
                // A middleware that returns false has already sent the response.
                if ($this->invokeMiddleware($middleware, $request) === false) {
                    return;
                }
            }

            $this->invoke($route['handler'], $request);

            return;
        }

        if ($allowedMethods !== []) {
            Response::error(
                'Method not allowed',
                null,
                Response::METHOD_NOT_ALLOWED,
                ['Allow' => implode(', ', array_unique($allowedMethods))]
            );

            return;
        }

        Response::error('Route not found', null, Response::NOT_FOUND);
    }

    private function add(string $method, string $path, mixed $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => $method,
            'regex' => $this->compile($path),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Turn /users/{id} into a regular expression with named groups.
     */
    private function compile(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        $pattern = preg_replace('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', '(?P<$1>[^/]+)', $normalized);

        return '#^' . $pattern . '$#';
    }

    /**
     * Remove the base path (/javascript) so routes stay deployment independent.
     */
    private function stripBasePath(string $path): string
    {
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $stripped = substr($path, strlen($this->basePath));

            if ($stripped === '' || str_starts_with($stripped, '/')) {
                return $stripped === '' ? '/' : $stripped;
            }
        }

        return $path;
    }

    /**
     * Run one middleware handler. Middleware must be static (or a Closure) and
     * returns false to stop the request - it is then responsible for having
     * sent the response, e.g. App\Middleware\AuthMiddleware::authenticate().
     */
    private function invokeMiddleware(mixed $middleware, Request $request): bool
    {
        if ($middleware instanceof Closure) {
            return $middleware($request) !== false;
        }

        if (is_array($middleware) && count($middleware) === 2) {
            [$class, $method] = array_values($middleware);

            if (is_string($class) && is_string($method) && class_exists($class) && method_exists($class, $method)) {
                return $class::$method($request) !== false;
            }
        }

        if (is_string($middleware) && str_contains($middleware, '@')) {
            [$class, $method] = explode('@', $middleware, 2);

            if (class_exists($class) && method_exists($class, $method)) {
                return $class::$method($request) !== false;
            }
        }

        Response::error('Route middleware is not callable', null, Response::INTERNAL_SERVER_ERROR);

        return false;
    }

    private function invoke(mixed $handler, Request $request): void
    {
        if ($handler instanceof Closure) {
            $handler($request);

            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = array_values($handler);

            if (is_string($class) && is_string($method) && class_exists($class) && method_exists($class, $method)) {
                (new $class())->{$method}($request);

                return;
            }
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);

            if (class_exists($class) && method_exists($class, $method)) {
                (new $class())->{$method}($request);

                return;
            }
        }

        Response::error('Route handler is not callable', null, Response::INTERNAL_SERVER_ERROR);
    }
}