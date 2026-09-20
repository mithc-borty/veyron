<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * Every request to the API enters the application here. The file only wires
 * the application together: autoloading, environment, error handling, routing
 * and dispatch. Business logic lives in app/.
 */

use App\Config\App;
use App\Core\Autoloader;
use App\Core\ExceptionHandler;
use App\Core\Request;
use App\Core\Router;

define('BASE_PATH', __DIR__);

// Composer autoloader: loads vendor/ packages and the framework's own
// composer.json PSR-4 namespaces (MithcBorty\Veyron\ => src/).
require BASE_PATH . '/vendor/autoload.php';

// Application PSR-4 autoloader: App\Core\Router => app/Core/Router.php.
// Kept alongside Composer: it owns the App\ namespace while Composer owns
// vendor/ packages and MithcBorty\Veyron\ (no prefix overlap => no duplicates).
require BASE_PATH . '/app/Core/Autoloader.php';

Autoloader::register('App\\', BASE_PATH . '/app');

// Global helpers: base_path(), storage_path(), env(), app_debug().
require BASE_PATH . '/app/Helpers/helpers.php';

App::load(BASE_PATH . '/.env');

/*
 * The API always answers with JSON, so raw PHP errors must never reach the
 * client. They are logged (see App\Core\Logger / storage/logs) instead.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Uncaught exceptions and fatal errors are converted into JSON responses.
ExceptionHandler::register();

/*
 * Base path of the application, derived from the front controller location:
 *   /index.php            => ''           (project as document root, e.g. javascript.local)
 *   /javascript/index.php => /javascript  (htdocs/javascript)
 */
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$basePath   = rtrim(dirname($scriptName), '/');

try {
    $request = Request::capture();

    $router = new Router($basePath);

    // routes/api.php returns a callable that registers every API route.
    $routes = require BASE_PATH . '/routes/api.php';
    $routes($router);

    // routes/web.php registers the server-rendered pages (/ , /login , ...).
    $webRoutes = require BASE_PATH . '/routes/web.php';
    $webRoutes($router);

    $router->dispatch($request);
} catch (Throwable $exception) {
    ExceptionHandler::handle($exception);
}