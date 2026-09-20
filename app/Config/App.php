<?php

declare(strict_types=1);

namespace App\Config;

/**
 * App configuration.
 *
 * This class has two jobs:
 *   1. Load key/value pairs from the .env file into memory.
 *   2. Expose small, readable configuration helpers.
 *
 * We keep it intentionally simple. There is no dependency injection
 * container and no complex config caching. A beginner can read this
 * file top-to-bottom and understand exactly what happens.
 */
final class App
{
    /**
     * Whether the .env file has already been loaded.
     */
    private static bool $loaded = false;

    /**
     * All environment values loaded from .env, keyed by name.
     *
     * @var array<string, string>
     */
    private static array $env = [];

    /**
     * Load the .env file once.
     *
     * Lines that are empty or start with "#" are ignored.
     * Values wrapped in double quotes have the quotes stripped.
     * Existing real environment variables are NOT overwritten.
     *
     * @param string $envPath Absolute path to the .env file.
     */
    public static function load(string $envPath): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_file($envPath)) {
            // No .env file yet: the application still runs using defaults.
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip blank lines and comments.
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Only handle simple KEY=VALUE lines.
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key   = trim($key);
            $value = trim($value);

            // Remove surrounding double quotes if present.
            if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
                $value = substr($value, 1, -1);
            }

            self::$env[$key] = $value;
        }
    }

    /**
     * Read an environment value, falling back to a default.
     *
     * A real environment variable (e.g. set by the web server) always
     * wins over the value found in the .env file.
     */
    public static function env(string $key, ?string $default = null): ?string
    {
        $fromServer = getenv($key);

        if ($fromServer !== false) {
            return $fromServer;
        }

        return self::$env[$key] ?? $default;
    }

    /**
     * Read a boolean environment value.
     *
     * "true", "1", "yes" and "on" are treated as true.
     */
    public static function envBool(string $key, bool $default = false): bool
    {
        $value = self::env($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Convenience helpers for commonly used values.
     */
    public static function name(): string
    {
        return self::env('APP_NAME', 'PHP REST API') ?? 'PHP REST API';
    }

    public static function environment(): string
    {
        return self::env('APP_ENV', 'production') ?? 'production';
    }

    public static function isDebug(): bool
    {
        return self::envBool('APP_DEBUG', false);
    }

    public static function url(): string
    {
        return self::env('APP_URL', 'http://javascript.local') ?? 'http://javascript.local';
    }

    public static function apiVersion(): string
    {
        return self::env('API_VERSION', 'v1') ?? 'v1';
    }

    /**
     * Lifetime of a newly issued API token, in days (API_TOKEN_EXPIRY_DAYS).
     */
    public static function apiTokenExpiryDays(): int
    {
        $days = (int) (self::env('API_TOKEN_EXPIRY_DAYS', '30') ?? '30');

        return $days > 0 ? $days : 30;
    }

    /**
     * Secret used to sign JWTs (JWT_SECRET). Null when not configured, in
     * which case JWT issuance/verification is unavailable.
     */
    public static function jwtSecret(): ?string
    {
        $secret = trim((string) (self::env('JWT_SECRET', '') ?? ''));

        return $secret !== '' ? $secret : null;
    }

    /**
     * JWT lifetime in seconds. JWTs share the API token lifetime so both
     * token flavours expire together.
     */
    public static function jwtTtlSeconds(): int
    {
        return self::apiTokenExpiryDays() * 86400;
    }

    /**
     * Name of the HttpOnly cookie carrying the JWT for browser clients.
     */
    public static function jwtCookieName(): string
    {
        $name = trim((string) (self::env('JWT_COOKIE', 'access_token') ?? ''));

        return $name !== '' ? $name : 'access_token';
    }
}