<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Requires a valid session, JWT or legacy bearer token.
 *
 * Session-authenticated state-changing requests (POST/PUT/PATCH/DELETE) must
 * also send the session CSRF token in the X-CSRF-TOKEN header. Bearer tokens
 * are immune to cross-site attacks, so no CSRF check applies to them.
 *
 * Returning false stops the request; the error response has already been sent
 * by this middleware (that is why Router checks for an explicit false).
 */
final class AuthMiddleware
{
    private function __construct()
    {
    }

    public static function authenticate(Request $request): bool
    {
        if (Auth::user($request) === null) {
            Response::error(
                'Unauthenticated',
                null,
                Response::UNAUTHORIZED,
                ['WWW-Authenticate' => 'Bearer']
            );

            return false;
        }

        // CSRF check only for cookie-based auth on writes: a foreign site can
        // trigger the cookie but cannot read the CSRF token.
        if (Auth::isSessionAuth() && self::isWriteMethod($request)) {
            if (Session::validateCsrf($request->header(Session::CSRF_HEADER))) {
                return true;
            }

            Response::error(
                'Invalid CSRF token',
                ['csrf' => 'Send the session CSRF token in the ' . Session::CSRF_HEADER . ' header.'],
                Response::FORBIDDEN
            );

            return false;
        }

        return true;
    }

    /**
     * Methods that change state (logout writes by revoking credentials).
     */
    private static function isWriteMethod(Request $request): bool
    {
        return !$request->isMethod('GET') && !$request->isMethod('HEAD') && !$request->isMethod('OPTIONS');
    }
}