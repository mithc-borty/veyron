<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use App\Models\User;
use PDOException;
use RuntimeException;

/**
 * User management: pagination, create, update (PUT/PATCH) and delete.
 *
 * The service returns result arrays; controllers turn them into HTTP
 * responses. Password hashes never leave this layer.
 */
final class UserService
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    /**
     * @param array<string, mixed> $query
     * @return array{data: array<int, array<string, mixed>>, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    public function paginate(array $query): array
    {
        [$page, $perPage] = self::pagination($query);

        $total = (int) (Database::selectOne('SELECT COUNT(*) AS c FROM `users`')['c'] ?? 0);
        $offset = ($page - 1) * $perPage;

        // $page/$perPage are integers clamped by pagination(), so inlining them
        // is safe - PDO would bind LIMIT placeholders as strings otherwise.
        $rows = Database::select(
            'SELECT `id`, `name`, `email`, `role`, `status`, `created_at`, `updated_at`'
            . ' FROM `users` ORDER BY `id` ASC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );

        return [
            'data' => array_map([User::class, 'toPublic'], $rows),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * page/per_page with sane limits (1..100, default 20).
     *
     * @param array<string, mixed> $query
     * @return array{0: int, 1: int}
     */
    public static function pagination(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $requested = (int) ($query['per_page'] ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min(self::MAX_PER_PAGE, $requested > 0 ? $requested : self::DEFAULT_PER_PAGE));

        return [$page, $perPage];
    }

    /**
     * @return array<string, mixed>|null Public representation or null.
     */
    public function find(int $id): ?array
    {
        $user = User::find($id);

        return $user === null ? null : User::toPublic($user);
    }

    /**
     * Admin-only creation: an administrator may set role and status.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, user: array<string, mixed>|null}
     */
    public function create(array $input): array
    {
        $input['name'] = trim((string) ($input['name'] ?? ''));
        $input['email'] = AuthService::normalizeEmail((string) ($input['email'] ?? ''));

        $errors = Validator::validate($input, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|string|email|max:255|unique:' . User::TABLE . ',email',
            // Confirmation is a form-flow concern (register): the API sets
            // passwords outright.
            'password' => 'required|string|min:8|max:72',
            'role' => 'nullable|in:admin,user',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($errors !== []) {
            return ['errors' => $errors, 'user' => null];
        }

        $hash = password_hash((string) $input['password'], PASSWORD_DEFAULT);

        if ($hash === false) {
            throw new RuntimeException('Unable to hash the password.');
        }

        try {
            $id = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $hash,
                'role' => (string) (($input['role'] ?? '') !== '' ? $input['role'] : User::ROLE_USER),
                'status' => (string) (($input['status'] ?? '') !== '' ? $input['status'] : User::STATUS_ACTIVE),
            ]);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return ['errors' => ['email' => 'The email field has already been taken.'], 'user' => null];
            }

            throw $exception;
        }

        return $this->result($id);
    }

    /**
     * PUT (full replace) and PATCH (partial) share this method.
     *
     * @param array<string, mixed> $input
     * @param bool $partial       true for PATCH: only supplied fields change.
     * @param bool $canChangeRole true for administrators: role/status allowed.
     * @return array{errors: array<string, string>, user: array<string, mixed>|null} user is null when the id does not exist.
     */
    public function update(int $id, array $input, bool $partial, bool $canChangeRole): array
    {
        if (User::find($id) === null) {
            return ['errors' => [], 'user' => null];
        }

        $input['name'] = trim((string) ($input['name'] ?? ''));
        $input['email'] = AuthService::normalizeEmail((string) ($input['email'] ?? ''));

        $presence = $partial ? 'nullable' : 'required';

        $errors = Validator::validate($input, [
            'name' => $presence . '|string|min:2|max:100',
            'email' => $presence . '|string|email|max:255|unique:' . User::TABLE . ',email,' . $id,
            // No `confirmed` here: API clients change passwords outright.
            'password' => 'nullable|string|min:8|max:72',
            'role' => 'nullable|in:admin,user',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($errors !== []) {
            return ['errors' => $errors, 'user' => null];
        }

        $assignments = [];
        $params = [];

        if (($input['name'] ?? '') !== '') {
            $assignments[] = '`name` = ?';
            $params[] = (string) $input['name'];
        }

        if (($input['email'] ?? '') !== '') {
            $assignments[] = '`email` = ?';
            $params[] = (string) $input['email'];
        }

        // Only an administrator may change role or status.
        if ($canChangeRole && ($input['role'] ?? '') !== '') {
            $assignments[] = '`role` = ?';
            $params[] = (string) $input['role'];
        }

        if ($canChangeRole && ($input['status'] ?? '') !== '') {
            $assignments[] = '`status` = ?';
            $params[] = (string) $input['status'];
        }

        if ((string) ($input['password'] ?? '') !== '') {
            $hash = password_hash((string) $input['password'], PASSWORD_DEFAULT);

            if ($hash === false) {
                throw new RuntimeException('Unable to hash the password.');
            }

            $assignments[] = '`password` = ?';
            $params[] = $hash;
        }

        if ($assignments !== []) {
            $params[] = $id;
            Database::execute('UPDATE `users` SET ' . implode(', ', $assignments) . ' WHERE `id` = ?', $params);
        }

        return $this->result($id);
    }

    /**
     * Delete a user; the api_tokens foreign key removes its tokens with it.
     */
    public function delete(int $id): bool
    {
        return Database::execute('DELETE FROM `users` WHERE `id` = ?', [$id]) === 1;
    }

    /**
     * @return array{errors: array<string, string>, user: array<string, mixed>|null}
     */
    private function result(int $id): array
    {
        $user = User::find($id);

        if ($user === null) {
            throw new RuntimeException('The user could not be read back.');
        }

        return ['errors' => [], 'user' => User::toPublic($user)];
    }
}