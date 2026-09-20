<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Native PHP session wrapper (cookie based) for browser clients.
 *
 * API clients keep using bearer tokens (JWT, preferred); browsers can rely on
 * the session cookie set at login instead. The session only ever stores the
 * user id and a CSRF token - never passwords or bearer tokens.
 *
 * The session starts lazily: read methods return null/false without starting
 * (and without sending a cookie) when the request carries no session cookie.
 */
final class Session
{
    /** Header carrying the CSRF token on session-authenticated writes. */
    public const CSRF_HEADER = 'X-CSRF-TOKEN';

    private const USER_KEY = 'user_id';
    private const CSRF_KEY = 'csrf_token';

    private function __construct()
    {
    }

    /**
     * Start the session with safe cookie defaults. No-op when already active,
     * when sessions are disabled, or when headers have already been sent.
     */
    public static function start(): void
    {
        if (PHP_SAPI === 'cli') {
            // CLI / test harness: keep the $_SESSION superglobal usable but
            // never touch session files, cookies or headers.
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_DISABLED) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_set_cookie_params([
            'lifetime' => 0, // Browser-session cookie: gone when the browser closes.
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    /**
     * The logged-in user id, or null. Sends no cookie when the request carries
     * no session cookie.
     */
    public static function userId(): ?int
    {
        if (PHP_SAPI === 'cli') {
            // CLI / test harness: no cookies or session files, the
            // $_SESSION superglobal is the whole session.
            $id = $_SESSION[self::USER_KEY] ?? null;

            if (is_int($id)) {
                return $id;
            }

            $asString = (string) ($id ?? '');

            return $asString !== '' && ctype_digit($asString) ? (int) $asString : null;
        }

        if (session_status() !== PHP_SESSION_ACTIVE && !self::hasCookie()) {
            return null;
        }

        self::start();

        $id = $_SESSION[self::USER_KEY] ?? null;

        if (is_int($id)) {
            return $id;
        }

        $asString = (string) ($id ?? '');

        return $asString !== '' && ctype_digit($asString) ? (int) $asString : null;
    }

    public static function hasUser(): bool
    {
        return self::userId() !== null;
    }

    /**
     * Establish the session after a successful login: regenerates the id
     * (against session fixation) and ensures a CSRF token exists.
     */
    public static function login(int $userId): void
    {
        self::start();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION[self::USER_KEY] = $userId;

        // Always rotate: the CSRF token must change on a privilege change
        // (anonymous -> logged in), not only when it does not exist yet.
        $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
    }

    /**
     * Destroy the session. Returns true when a user was logged in via it.
     */
    public static function logout(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !self::hasCookie()) {
            $hadUser = isset($_SESSION[self::USER_KEY]);

            if (PHP_SAPI === 'cli') {
                $_SESSION = [];

                return $hadUser;
            }

            return false;
        }

        self::start();

        $hadUser = isset($_SESSION[self::USER_KEY]);

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        // Drop the cookie on this response so the browser stops sending it.
        if (!headers_sent() && isset($_COOKIE[session_name()])) {
            setcookie((string) session_name(), '', time() - 3600, '/');
        }

        return $hadUser;
    }

    /**
     * Forget only the user id (used when the session points at a user that no
     * longer exists or was deactivated).
     */
    public static function forgetUser(): void
    {
        self::start();

        unset($_SESSION[self::USER_KEY]);
    }

    /**
     * The CSRF token the browser must send back in Session::CSRF_HEADER on
     * state-changing requests.
     */
    public static function csrfToken(): string
    {
        self::start();

        if (empty($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CSRF_KEY];
    }

    public static function validateCsrf(?string $token): bool
    {
        if (PHP_SAPI === 'cli') {
            $expected = $_SESSION[self::CSRF_KEY] ?? null;

            if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
                return false;
            }

            return hash_equals($expected, $token);
        }

        if (session_status() !== PHP_SESSION_ACTIVE && !self::hasCookie()) {
            return false;
        }

        self::start();

        $expected = $_SESSION[self::CSRF_KEY] ?? null;

        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    private static function hasCookie(): bool
    {
        $name = session_name();

        return is_string($name) && isset($_COOKIE[$name]);
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
