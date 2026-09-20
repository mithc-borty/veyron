<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;
use App\Services\AuthService;

/**
 * Server-rendered web pages (routes/web.php).
 *
 * Browser flows use the session cookie, not bearer tokens, and plain HTML
 * forms instead of JSON. Writes go through the session CSRF token; every
 * template escapes output with e().
 */
final class WebController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();

        // Available in every template.
        View::share(['appName' => App::name()]);
    }

    /**
     * GET / - public landing page.
     */
    public function home(Request $request): void
    {
        Response::html(view('home', [
            'title' => 'Welcome',
            'loggedIn' => Session::hasUser(),
        ], 'layouts.app'));
    }

    /**
     * GET /login - show the login form.
     */
    public function showLogin(Request $request): void
    {
        if (Session::hasUser()) {
            Response::redirect(url('/dashboard'));

            return;
        }

        Response::html(view('login', [
            'title' => 'Login',
            'error' => null,
            'email' => '',
        ], 'layouts.app'));
    }

    /**
     * POST /login - verify credentials and start the session.
     */
    public function login(Request $request): void
    {
        if (!Session::validateCsrf((string) $request->form('_token', ''))) {
            $this->renderLogin('Your session expired. Please try again.', '', 403);

            return;
        }

        $result = $this->authService->attempt([
            'email' => (string) $request->form('email', ''),
            'password' => (string) $request->form('password', ''),
        ]);

        if ($result['errors'] !== []) {
            $this->renderLogin('Email and password are required.', (string) $request->form('email', ''), 422);

            return;
        }

        if ($result['failure'] === AuthService::FAILURE_INACTIVE) {
            $this->renderLogin('Your account is inactive.', (string) $request->form('email', ''), 403);

            return;
        }

        // Unknown email and wrong password produce the same message.
        if ($result['failure'] !== null || $result['user'] === null) {
            $this->renderLogin('These credentials do not match our records.', (string) $request->form('email', ''), 401);

            return;
        }

        Session::login((int) $result['user']['id']);

        Response::redirect(url('/dashboard'));
    }

    /**
     * GET /dashboard - authenticated area (session cookie).
     */
    public function dashboard(Request $request): void
    {
        $user = $this->sessionUser();

        if ($user === null) {
            Response::redirect(url('/login'));

            return;
        }

        Response::html(view('dashboard', [
            'title' => 'Dashboard',
            'user' => User::toPublic($user),
        ], 'layouts.app'));
    }

    /**
     * POST /logout - destroy the session.
     */
    public function logout(Request $request): void
    {
        if (!Session::validateCsrf((string) $request->form('_token', ''))) {
            Response::redirect(url('/'));

            return;
        }

        Session::logout();

        Response::redirect(url('/'));
    }

    /**
     * The session user, or null when absent / no longer valid. Drops the
     * stale session instead of leaving it pointing at a missing or inactive
     * account.
     *
     * @return array<string, mixed>|null
     */
    private function sessionUser(): ?array
    {
        $userId = Session::userId();

        if ($userId === null) {
            return null;
        }

        $user = User::find($userId);

        if ($user === null || ($user['status'] ?? null) !== User::STATUS_ACTIVE) {
            Session::logout();

            return null;
        }

        return $user;
    }

    /**
     * Re-render the login form with an error message.
     */
    private function renderLogin(string $error, string $email, int $status): void
    {
        Response::html(view('login', [
            'title' => 'Login',
            'error' => $error,
            'email' => $email,
        ], 'layouts.app'), $status);
    }
}
