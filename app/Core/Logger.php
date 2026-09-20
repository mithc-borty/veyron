<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\App;
use Throwable;

/**
 * Tiny file logger writing to storage/logs/app-YYYY-MM-DD.log.
 *
 * Two rules are enforced here:
 *   - passwords, tokens, secrets and credentials are redacted;
 *   - logging never breaks a request (write failures fall back to error_log).
 *
 * No external logging package is used.
 */
final class Logger
{
    private const LEVELS = ['debug', 'info', 'warning', 'error'];

    /**
     * Context keys containing one of these fragments are redacted.
     */
    private const SENSITIVE_FRAGMENTS = ['password', 'token', 'secret', 'authorization', 'credential'];

    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_CONTEXT_LENGTH = 4000;

    private function __construct()
    {
    }

    public static function debug(string $message, array $context = []): void
    {
        if (App::isDebug()) {
            self::write('debug', $message, $context);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * Absolute path of the log directory (created when missing).
     */
    public static function directory(): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $directory = $base . '/storage/logs';

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        return $directory;
    }

    private static function write(string $level, string $message, array $context): void
    {
        if (!in_array($level, self::LEVELS, true)) {
            $level = 'info';
        }

        $line = sprintf(
            '[%s] %s: %s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            self::scrub($message),
            $context === [] ? '' : ' ' . self::encodeContext($context)
        ) . PHP_EOL;

        $path = self::directory() . '/app-' . date('Y-m-d') . '.log';

        if (@file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(rtrim($line));
        }
    }

    private static function encodeContext(array $context): string
    {
        $json = json_encode(
            self::redact($context),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return self::truncate($json === false ? '{}' : $json, self::MAX_CONTEXT_LENGTH);
    }

    /**
     * Recursively replace sensitive values.
     */
    private static function redact(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $clean[$key] = '[redacted]';
                continue;
            }

            $clean[$key] = is_array($value) ? self::redact($value) : self::stringify($value);
        }

        return $clean;
    }

    private static function stringify(mixed $value): mixed
    {
        if ($value instanceof Throwable) {
            return $value::class . ': ' . $value->getMessage();
        }

        if (is_object($value)) {
            return $value::class;
        }

        return is_string($value) ? self::truncate($value, 500) : $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Last safety net: never write a raw bearer token.
     */
    private static function scrub(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=:-]+/i', 'Bearer [redacted]', $message) ?? $message;

        return self::truncate(str_replace(["\r", "\n"], ' ', $message), self::MAX_MESSAGE_LENGTH);
    }

    private static function truncate(string $value, int $max): string
    {
        return strlen($value) > $max
            ? substr($value, 0, $max) . '...[truncated]'
            : $value;
    }
}