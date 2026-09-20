<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Authentication endpoints.
 *
 * Registration is complete: input is validated (including a unique email check
 * against the users table), the password is hashed with password_hash() and the
 * public user representation is returned. Passwords and hashes never appear in
 * a response.
 *
 * Login establishes every supported mode at once: the native PHP session
 * (cookie, for browsers) plus a bearer token (JWT preferred, legacy opaque
 * fallback). Logout clears whichever mode authenticated the request.
 */
final class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(Request $request): void
    {
        $result = $this->authService->register($request->body());

        if ($result['errors'] !== []) {
            Response::error('Validation failed', $result['errors'], Response::UNPROCESSABLE_ENTITY);

            return;
        }

        if ($result['user'] === null) {
            Response::error('Registration failed', null, Response::INTERNAL_SERVER_ERROR);

            return;
        }

        Response::success('Registration successful', $result['user'], Response::CREATED);
    }

    /**
     * POST /api/v1/auth/login
     *
     * Establishes the native session (browser cookie) and also returns a
     * bearer token once: a JWT when JWT_SECRET is configured, otherwise a
     * legacy opaque token. Only its hash is stored server side.
     */
    public function login(Request $request): void
    {
        $result = $this->authService->login($request->body());

        if ($result['errors'] !== []) {
            Response::error('Validation failed', $result['errors'], Response::UNPROCESSABLE_ENTITY);

            return;
        }

        if ($result['failure'] === AuthService::FAILURE_INACTIVE) {
            Response::error('Your account is inactive', null, Response::FORBIDDEN);

            return;
        }

        // Unknown email and wrong password produce the same response.
        if ($result['failure'] !== null) {
            Response::error('The provided credentials are incorrect', null, Response::UNAUTHORIZED);

            return;
        }

        Response::success('Login successful', [
            'user' => $result['user'],
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'csrf_token' => $result['csrf_token'],
        ]);
    }

    /**
     * POST /api/v1/auth/logout (authenticated) - revokes the bearer token in
     * use and destroys the session, whichever (or both) authenticated this
     * request.
     */
    public function logout(Request $request): void
    {
        $revokedBearer = Auth::revoke($request);
        $revokedSession = $this->authService->logoutSession();

        if (!$revokedBearer && !$revokedSession) {
            Response::error('Logout failed', null, Response::UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);

            return;
        }

        Response::success('Logged out successfully');
    }

    /**
     * GET /api/v1/auth/me (authenticated)
     */
    public function me(Request $request): void
    {
        $user = Auth::user($request);

        if ($user === null) {
            Response::error('Unauthenticated', null, Response::UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);

            return;
        }

        Response::success('User retrieved successfully', $this->authService->currentUser($user));
    }
}
