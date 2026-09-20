<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

/**
 * Session helpers for browser clients.
 *
 * Browsers authenticate through the session cookie set at login; write
 * requests then carry the CSRF token returned by this controller in the
 * X-CSRF-TOKEN header.
 */
final class SessionController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * GET /api/v1/auth/csrf and GET /api/v1/session/csrf (public).
     *
     * Returns the CSRF token for the caller's session. Calling it first also
     * establishes an anonymous session cookie, which lets a browser read the
     * token before it is logged in.
     */
    public function csrf(Request $request): void
    {
        $csrf = $this->authService->sessionCsrfToken();

        if ($csrf === '') {
            Session::start();
            $csrf = Session::csrfToken();
        }

        Response::success('CSRF token retrieved successfully', ['csrf_token' => $csrf]);
    }
}
