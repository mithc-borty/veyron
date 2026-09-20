<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Data access for the `api_tokens` table
 * (database/migrations/002_create_api_tokens_table.sql).
 *
 * Only the SHA-256 hash of a token is stored. The plaintext token exists once,
 * in the login response, and can never be read back from the database.
 */
final class ApiToken
{
    public const TABLE = 'api_tokens';

    private function __construct()
    {
    }

    /**
     * @param string $hash      sha256 hex of the plaintext token.
     * @param string $expiresAt 'Y-m-d H:i:s'
     * @return int The new token id.
     */
    public static function create(int $userId, string $hash, string $expiresAt): int
    {
        Database::execute(
            'INSERT INTO `api_tokens` (`user_id`, `token_hash`, `expires_at`) VALUES (?, ?, ?)',
            [$userId, $hash, $expiresAt]
        );

        return Database::lastInsertId();
    }

    /**
     * Find a token that has not expired yet.
     *
     * @return array<string, mixed>|null
     */
    public static function findValidByHash(string $hash): ?array
    {
        return Database::selectOne(
            'SELECT * FROM `api_tokens` WHERE `token_hash` = ? AND `expires_at` > NOW() LIMIT 1',
            [$hash]
        );
    }

    /**
     * Remember when the token was last used.
     */
    public static function touch(int $id): void
    {
        Database::execute('UPDATE `api_tokens` SET `last_used_at` = NOW() WHERE `id` = ?', [$id]);
    }

    /**
     * Revoke one token (logout).
     */
    public static function deleteByHash(string $hash): int
    {
        return Database::execute('DELETE FROM `api_tokens` WHERE `token_hash` = ?', [$hash]);
    }

    /**
     * Revoke every token of a user.
     */
    public static function deleteForUser(int $userId): int
    {
        return Database::execute('DELETE FROM `api_tokens` WHERE `user_id` = ?', [$userId]);
    }
}