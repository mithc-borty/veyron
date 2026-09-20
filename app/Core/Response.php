<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Centralised JSON responses.
 *
 * Every payload uses the same envelope:
 *
 *   success: {"success": true, "message": "...", "data": {...}}
 *   error:   {"success": false, "message": "...", "errors": {...}}
 */
final class Response
{
    public const OK = 200;
    public const CREATED = 201;
    public const NO_CONTENT = 204;
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const CONFLICT = 409;
    public const UNPROCESSABLE_ENTITY = 422;
    public const INTERNAL_SERVER_ERROR = 500;

    /**
     * Send a raw JSON payload with the given status code.
     *
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     */
    public static function json(array $payload, int $status = self::OK, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');

            foreach ($headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo self::encode($payload);
    }

    /**
     * Successful response with the standard envelope.
     */
    public static function success(string $message = '', mixed $data = null, int $status = self::OK): void
    {
        $payload = ['success' => true];

        if ($message !== '') {
            $payload['message'] = $message;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        self::json($payload, $status);
    }

    /**
     * Successful collection response with pagination metadata.
     *
     * @param array<int, mixed>     $data
     * @param array<string, mixed>  $meta
     */
    public static function collection(array $data, array $meta, string $message = ''): void
    {
        $payload = ['success' => true];

        if ($message !== '') {
            $payload['message'] = $message;
        }

        $payload['data'] = $data;
        $payload['meta'] = $meta;

        self::json($payload, self::OK);
    }

    /**
     * Error response with the standard envelope.
     *
     * @param array<string, mixed>|null $errors
     * @param array<string, string>     $headers
     */
    public static function error(
        string $message,
        ?array $errors = null,
        int $status = self::BAD_REQUEST,
        array $headers = []
    ): void {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        self::json($payload, $status, $headers);
    }

    /**
     * 204 response: no body at all.
     */
    public static function noContent(): void
    {
        if (!headers_sent()) {
            http_response_code(self::NO_CONTENT);
        }
    }

    /**
     * HTML response for web pages rendered by App\Core\View.
     *
     * @param array<string, string> $headers
     */
    public static function html(string $html, int $status = self::OK, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');

            foreach ($headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $html;
    }

    /**
     * HTTP redirect (302 by default).
     */
    public static function redirect(string $to, int $status = 302): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Location: ' . $to, true);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function encode(array $payload): string
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $json === false
            ? '{"success":false,"message":"Internal server error"}'
            : $json;
    }
}