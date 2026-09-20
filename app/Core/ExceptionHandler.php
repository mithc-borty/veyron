<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\App;
use JsonException;
use Throwable;

/**
 * Centralised error handling.
 *
 * Every unhandled error becomes a JSON response. Internal details are written
 * to the log file and are never placed in the response body, so stack traces,
 * SQL statements, credentials and filesystem paths cannot leak to a client.
 */
final class ExceptionHandler
{
    private function __construct()
    {
    }

    /**
     * Route uncaught exceptions and fatal errors through this class.
     */
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        register_shutdown_function([self::class, 'handleFatalError']);
    }

    public static function handle(Throwable $exception): void
    {
        [$status, $message] = self::classify($exception);

        self::log($exception, $status);

        Response::error($message, null, $status);
    }

    /**
     * Map an exception to an HTTP status code and a safe client message.
     *
     * @return array{0: int, 1: string}
     */
    private static function classify(Throwable $exception): array
    {
        return match (true) {
            // Malformed JSON request body.
            $exception instanceof JsonException => [Response::BAD_REQUEST, 'Invalid JSON request body'],
            // Anything else (PDO errors, type errors, ...) stays internal.
            default => [Response::INTERNAL_SERVER_ERROR, 'Internal server error'],
        };
    }

    private static function log(Throwable $exception, int $status): void
    {
        $context = [
            'type' => $exception::class,
            'status' => $status,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile() . ':' . $exception->getLine(),
        ];

        // Development convenience only; still written to the log file, never
        // to the response.
        if (App::isDebug()) {
            $context['trace'] = $exception->getTraceAsString();
        }

        if ($status >= 500) {
            Logger::error('Unhandled exception', $context);

            return;
        }

        Logger::warning('Request rejected', $context);
    }

    /**
     * Last resort for fatal errors (E_ERROR, E_PARSE, ...).
     */
    public static function handleFatalError(): void
    {
        $error = error_get_last();

        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        Logger::error('Fatal error', [
            'message' => $error['message'],
            'file' => $error['file'] . ':' . $error['line'],
        ]);

        // Never append to a response that was already sent.
        if (headers_sent()) {
            return;
        }

        Response::error('Internal server error', null, Response::INTERNAL_SERVER_ERROR);
    }
}