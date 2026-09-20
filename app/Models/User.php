<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Data access for the `users` table (database/migrations/001_create_users_table.sql).
 *
 * Every query uses a prepared statement. A row returned by find()/findByEmail()
 * contains the password hash, so use toPublic() before leaking a user to a
 * client.
 */
final class User
{
    public const TABLE = 'users';

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /**
     * Columns that may be sent to an API client (password is not one of them).
     *
     * @var array<int, string>
     */
    public const PUBLIC_COLUMNS = ['id', 'name', 'email', 'role', 'status', 'created_at', 'updated_at'];

    private function __construct()
    {
    }

    /**
     * Insert a user. The caller must pass an already hashed password.
     *
     * @param array<string, mixed> $attributes
     * @return int The new user id.
     */
    public static function create(array $attributes): int
    {
        Database::execute(
            'INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES (?, ?, ?, ?, ?)',
            [
                (string) $attributes['name'],
                (string) $attributes['email'],
                (string) $attributes['password'],
                (string) ($attributes['role'] ?? self::ROLE_USER),
                (string) ($attributes['status'] ?? self::STATUS_ACTIVE),
            ]
        );

        return Database::lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM `users` WHERE `id` = ? LIMIT 1', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        return Database::selectOne('SELECT * FROM `users` WHERE `email` = ? LIMIT 1', [$email]);
    }

    /**
     * Strip private columns (password hash) before the user is returned to a client.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function toPublic(array $user): array
    {
        $public = [];

        foreach (self::PUBLIC_COLUMNS as $column) {
            if (array_key_exists($column, $user)) {
                $public[$column] = $user[$column];
            }
        }

        return $public;
    }
}