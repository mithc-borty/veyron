<?php

declare(strict_types=1);

/**
 * Web routes: server-rendered pages (App\Controllers\WebController).
 *
 * These live next to the JSON API routes (routes/api.php); the paths do not
 * overlap, so both files can register into the same Router instance.
 */

use App\Controllers\WebController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [WebController::class, 'home']);
    $router->get('/login', [WebController::class, 'showLogin']);
    $router->post('/login', [WebController::class, 'login']);
    $router->get('/dashboard', [WebController::class, 'dashboard']);
    $router->post('/logout', [WebController::class, 'logout']);
};
