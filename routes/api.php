<?php

declare(strict_types=1);

/**
 * API routes (version v1).
 *
 * This file is required by index.php and returns a callable that receives the
 * Router instance. Paths are written without the application base path, so the
 * routes work for any deployment (project as document root, or /javascript).
 *
 * Middleware handlers are static and run before the controller.
 */

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\SessionController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

return static function (Router $router): void {
    $auth = [[AuthMiddleware::class, 'authenticate']];
    $admin = [[AuthMiddleware::class, 'authenticate'], [RoleMiddleware::class, 'admin']];

    // Public.
    $router->get('/api/v1/health', [HealthController::class, 'index']);
    $router->post('/api/v1/auth/register', [AuthController::class, 'register']);
    $router->post('/api/v1/auth/login', [AuthController::class, 'login']);
    $router->get('/api/v1/auth/csrf', [SessionController::class, 'csrf']);
    $router->get('/api/v1/session/csrf', [SessionController::class, 'csrf']);

    // Authenticated.
    $router->post('/api/v1/auth/logout', [AuthController::class, 'logout'], $auth);
    $router->get('/api/v1/auth/me', [AuthController::class, 'me'], $auth);

    $router->get('/api/v1/users', [UserController::class, 'index'], $auth);
    $router->get('/api/v1/users/{id}', [UserController::class, 'show'], $auth);
    $router->post('/api/v1/users', [UserController::class, 'store'], $admin);
    $router->put('/api/v1/users/{id}', [UserController::class, 'update'], $auth);
    $router->patch('/api/v1/users/{id}', [UserController::class, 'patch'], $auth);
    $router->delete('/api/v1/users/{id}', [UserController::class, 'destroy'], $auth);
};
