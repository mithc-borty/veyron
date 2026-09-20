<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\UserService;

/**
 * User CRUD endpoints. Every route requires a bearer token (see routes/api.php).
 *
 *   GET    /users        any authenticated user
 *   GET    /users/{id}   any authenticated user
 *   POST   /users        administrators only (RoleMiddleware)
 *   PUT    /users/{id}   the user itself or an administrator
 *   PATCH  /users/{id}   the user itself or an administrator
 *   DELETE /users/{id}   the user itself or an administrator
 *
 * Changing "role" or "status" is restricted to administrators inside UserService.
 */
final class UserController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    /**
     * GET /api/v1/users?page=1&per_page=20
     */
    public function index(Request $request): void
    {
        $result = $this->userService->paginate($request->queryParams());

        Response::collection($result['data'], $result['meta'], 'Users retrieved successfully');
    }

    /**
     * GET /api/v1/users/{id}
     */
    public function show(Request $request): void
    {
        $user = $this->userService->find(self::id($request));

        if ($user === null) {
            Response::error('User not found', null, Response::NOT_FOUND);

            return;
        }

        Response::success('User retrieved successfully', $user);
    }

    /**
     * POST /api/v1/users (administrator only)
     */
    public function store(Request $request): void
    {
        $result = $this->userService->create($request->body());

        if ($result['errors'] !== []) {
            Response::error('Validation failed', $result['errors'], Response::UNPROCESSABLE_ENTITY);

            return;
        }

        Response::success('User created successfully', $result['user'], Response::CREATED);
    }

    /**
     * PUT /api/v1/users/{id}
     */
    public function update(Request $request): void
    {
        $this->save($request, false);
    }

    /**
     * PATCH /api/v1/users/{id}
     */
    public function patch(Request $request): void
    {
        $this->save($request, true);
    }

    /**
     * DELETE /api/v1/users/{id}
     */
    public function destroy(Request $request): void
    {
        $id = self::id($request);

        if (self::authorize($request, $id) === false) {
            return;
        }

        if (!$this->userService->delete($id)) {
            Response::error('User not found', null, Response::NOT_FOUND);

            return;
        }

        Response::success('User deleted successfully');
    }

    private function save(Request $request, bool $partial): void
    {
        $id = self::id($request);
        $current = self::authorize($request, $id);

        if ($current === false) {
            return;
        }

        $result = $this->userService->update(
            $id,
            $request->body(),
            $partial,
            ((string) ($current['role'] ?? '')) === User::ROLE_ADMIN
        );

        if ($result['errors'] !== []) {
            Response::error('Validation failed', $result['errors'], Response::UNPROCESSABLE_ENTITY);

            return;
        }

        if ($result['user'] === null) {
            Response::error('User not found', null, Response::NOT_FOUND);

            return;
        }

        Response::success('User updated successfully', $result['user']);
    }

    /**
     * The authenticated user must be the record owner or an administrator.
     *
     * @return array<string, mixed>|false The authenticated user, or false when a
     *                                   401/403 response has already been sent.
     */
    private static function authorize(Request $request, int $id): array|false
    {
        $current = Auth::user($request);

        if ($current === null) {
            Response::error('Unauthenticated', null, Response::UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);

            return false;
        }

        $isAdmin = ((string) ($current['role'] ?? '')) === User::ROLE_ADMIN;

        if (!$isAdmin && (int) $current['id'] !== $id) {
            Response::error('Forbidden', null, Response::FORBIDDEN);

            return false;
        }

        return $current;
    }

    private static function id(Request $request): int
    {
        return (int) $request->routeParam('id', 0);
    }
}