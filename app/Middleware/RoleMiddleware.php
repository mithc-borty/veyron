<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Role based authorization. Only the admin-only case is needed right now:
 * finer rules ("self or admin") are handled inside UserController where the
 * route parameter is available.
 */
final class RoleMiddleware
{
    private function __construct()
    {
    }

    /**
     * Requires an authenticated administrator (use after AuthMiddleware).
     */
    public static function admin(Request $request): bool
    {
        $user = Auth::user($request);

        if ($user === null) {
            Response::error(
                'Unauthenticated',
                null,
                Response::UNAUTHORIZED,
                ['WWW-Authenticate' => 'Bearer']
            );

            return false;
        }

        if (($user['role'] ?? null) !== User::ROLE_ADMIN) {
            Response::error('Forbidden', null, Response::FORBIDDEN);

            return false;
        }

        return true;
    }
}