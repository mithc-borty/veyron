<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Database configuration.
 *
 * Every value comes from the .env file (via App::env). No credential is ever
 * hard-coded in this class or anywhere else in the application.
 */
final class Database
{
    private function __construct()
    {
    }

    public static function host(): string
    {
        return App::env('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
    }

    public static function port(): int
    {
        return (int) (App::env('DB_PORT', '3306') ?? '3306');
    }

    public static function name(): string
    {
        return App::env('DB_DATABASE', 'javascript') ?? 'javascript';
    }

    public static function username(): string
    {
        return App::env('DB_USERNAME', 'root') ?? 'root';
    }

    /**
     * Never log, echo or expose the value returned here.
     */
    public static function password(): string
    {
        return App::env('DB_PASSWORD', '') ?? '';
    }

    public static function charset(): string
    {
        return App::env('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';
    }

    /**
     * PDO DSN. With $withDatabase = false the DSN targets the server only,
     * which is useful to check server connectivity before migrations ran.
     */
    public static function dsn(bool $withDatabase = true): string
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            self::host(),
            self::port(),
            self::charset()
        );

        if ($withDatabase) {
            $dsn .= ';dbname=' . self::name();
        }

        return $dsn;
    }
}