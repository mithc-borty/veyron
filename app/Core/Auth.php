<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\App;
use App\Models\ApiToken;
use App\Models\User;

/**
 * Authentication: native PHP session, JWT (preferred bearer token) and legacy
 * opaque tokens - no external packages.
 *
 * A request is authenticated by the first method that succeeds, in this order:
 *   1. Session cookie (browser clients): the logged-in user id is looked up.
 *      The user must still exist and be active, otherwise the stale id is dropped.
 *   2. "Authorization: Bearer <jwt>" (preferred for API clients): a HS256 JWT
 *      issued by Auth::issueJwt(). The signature and expiry are verified, then
 *      the stored token-hash row must still exist and be unexpired, so logout
 *      (deleting the row) revokes the JWT immediately.
 *   3. "Authorization: Bearer <64-hex-token>": a legacy opaque token issued by
 *      Auth::issueToken(). Still honoured for backward compatibility.
 *
 * Tokens are always stored as hashes; only a login response carries the
 * plaintext token (and only once). The session only stores the user id and a
 * CSRF token - never passwords or bearer tokens.
 *
 * Usage:
 *   $jwt   = Auth::issueJwt($user);          // login (preferred)
 *   $token = Auth::issueToken($user);        // login (legacy)
 *   $user  = Auth::user($request);           // authenticated user or null
 *   Auth::revoke($request);                  // logout (bearer)

 */
final class Auth
{
    private const TOKEN_BYTES = 32;

    /** jti length in bytes (hex rendered). */
    private const JWT_ID_BYTES = 16;

    /** Issuer claim written into our JWTs. */
    private const JWT_ISSUER = 'php-rest-api';

    private const AUTH_VIA_SESSION = 'session';
    private const AUTH_VIA_JWT = 'jwt';
    private const AUTH_VIA_OPAQUE = 'opaque';

    /**
     * Request-scoped cache: token hash => user row, so middleware and
     * controllers can both ask for the current user without a second query.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $resolved = [];

    private static ?string $authVia = null;

    private function __construct()
    {
    }

    /**
     * Did the current request authenticate through the session cookie? Only
     * session-based requests need CSRF protection on writes.
     */
    public static function isSessionAuth(): bool
    {
        return self::$authVia === self::AUTH_VIA_SESSION;
    }

    /**
     * Whether the request carries any bearer credential (used to decide if an
     * anonymous session cookie may be created for CSRF protection).
     */
    public static function hasBearerToken(Request $request): bool
    {
        return self::bearerToken($request) !== null;
    }

    /**
     * Issue a JWT (preferred bearer token) and store its hash so the token
     * can be revoked on logout. Null when no JWT_SECRET is configured.
     *
     * @param array<string, mixed> $user
     * @return array{token: string, expires_at: string}|null
     */
    public static function issueJwt(array $user): ?array
    {
        $secret = App::jwtSecret();

        if ($secret === null) {
            return null;
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + App::jwtTtlSeconds();
        // Unique per token so each login can be revoked independently.
        $tokenId = bin2hex(random_bytes(self::JWT_ID_BYTES));

        $token = Jwt::encode(
            [
                'iss' => self::JWT_ISSUER,
                'sub' => (int) $user['id'],
                'iat' => $issuedAt,
                'nbf' => $issuedAt,
                'exp' => $expiresAt,
                'jti' => $tokenId,
            ],
            $secret
        );

        ApiToken::create((int) $user['id'], self::hash($token), self::formatDateTime($expiresAt));

        return ['token' => $token, 'expires_at' => self::formatDateTime($expiresAt)];
    }

    /**
     * Issue and store a new opaque token for a user (legacy format, kept for
     * backward compatibility; new code should call issueJwt()).
     *
     * @param array<string, mixed> $user
     * @return array{token: string, expires_at: string}
     */
    public static function issueToken(array $user): array
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = (new \DateTimeImmutable('+' . App::apiTokenExpiryDays() . ' days'))->format('Y-m-d H:i:s');

        ApiToken::create((int) $user['id'], self::hash($token), $expiresAt);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * Whether the given string is a legacy opaque token (64 hex chars).
     */
    public static function isOpaqueToken(string $token): bool
    {
        return preg_match('/^[0-9a-f]{64}$/i', $token) === 1;
    }

    /**
     * Whether the given string has the three-segment JWT shape (cheap format
     * check only; signature and claims are verified by Jwt::decode()).
     */
    public static function isJwtShape(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $token) === 1;
    }

    /**
     * Plaintext bearer token from "Authorization: Bearer <token>", or null.
     */
    public static function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header === null) {
            return null;
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * The authenticated user for this request, or null when no method
     * succeeds. Order: session cookie, JWT bearer, legacy opaque bearer.
     *
     * A stale session (user missing or inactive) is dropped so the caller
     * falls through to bearer auth and eventually null. A successful session
     * user takes no CSRF-bearing side effects: it only reads.
     *
     * The returned row contains the password hash: always pass it through
     * User::toPublic() before returning it to a client.
     *
     * @return array<string, mixed>|null
     */
    public static function user(Request $request): ?array
    {
        $cacheKey = self::cacheKey($request);

        if ($cacheKey !== null && isset(self::$resolved[$cacheKey])) {
            return self::$resolved[$cacheKey];
        }

        $sessionUser = self::userFromSession();

        if ($sessionUser !== null) {
            self::$authVia = self::AUTH_VIA_SESSION;

            return $cacheKey !== null
                ? self::$resolved[$cacheKey] = $sessionUser
                : $sessionUser;
        }

        $user = self::userFromBearer($request);

        if ($user !== null && $cacheKey !== null) {
            self::$resolved[$cacheKey] = $user;
        }

        return $user;
    }

    /**
     * Authenticated user id from the session cookie, or null. Sessions whose
     * user no longer exists or is no longer active are forgotten.
     *
     * @return array<string, mixed>|null
     */
    private static function userFromSession(): ?array
    {
        $userId = Session::userId();

        if ($userId === null) {
            return null;
        }

        $user = User::find($userId);

        if ($user === null || ($user['status'] ?? null) !== User::STATUS_ACTIVE) {
            // Stale session: drop it server side, keep the cookie harmless.
            Session::forgetUser();
            Logger::debug('Stale session dropped', ['user_id' => $userId]);

            return null;
        }

        // Static cache key: every anonymous read would share it, so only
        // cache session users per id (never cache the null case).
        self::$resolved['session:' . $userId] = $user;

        Logger::debug('Session authenticated', ['user_id' => (int) $user['id']]);

        return $user;
    }

    /**
     * Validate a bearer token: a JWT issued by issueJwt() (preferred) or a
     * legacy opaque token. For JWTs the signature and claims are verified
     * first; both flavours then require a stored, unexpired token row, so
     * logout revokes them immediately.
     *
     * @return array<string, mixed>|null
     */
    private static function userFromBearer(Request $request): ?array
    {
        $token = self::bearerToken($request);

        if ($token === null) {
            self::$authVia = null;

            return null;
        }

        $hash = self::hash($token);

        if (isset(self::$resolved['bearer:' . $hash])) {
            return self::$resolved['bearer:' . $hash];
        }

        $isOpaque = self::isOpaqueToken($token);

        // Fast reject: not our JWT shape and not a legacy token.
        if (!$isOpaque && substr_count($token, '.') !== 2) {
            return null;
        }

        // Verify JWT signature and claims against our secret. A wrong secret
        // or a foreign JWT never reaches the database.
        if (!$isOpaque && !self::isOurJwt($token)) {
            return null;
        }

        $apiToken = ApiToken::findValidByHash($hash);

        if ($apiToken === null) {
            return null;
        }

        $user = User::find((int) $apiToken['user_id']);

        if ($user === null || ($user['status'] ?? null) !== User::STATUS_ACTIVE) {
            return null;
        }

        ApiToken::touch((int) $apiToken['id']);

        // Only the user id is logged - never the token.
        Logger::debug($isOpaque ? 'Bearer token authenticated' : 'JWT authenticated', ['user_id' => (int) $user['id']]);

        self::$authVia = $isOpaque ? self::AUTH_VIA_OPAQUE : self::AUTH_VIA_JWT;

        return self::$resolved['bearer:' . $hash] = $user;
    }

    /**
     * Whether the token is a JWT signed by this API (claim check on sub only;
     * signature and exp/nbf are fully verified by Jwt::decode()).
     */
    private static function isOurJwt(string $token): bool
    {
        $secret = App::jwtSecret();

        if ($secret === null) {
            return false;
        }

        $payload = Jwt::decode($token, $secret);

        if ($payload === null) {
            return false;
        }

        return ($payload['iss'] ?? null) === self::JWT_ISSUER
            && isset($payload['sub'])
            && (int) $payload['sub'] > 0;
    }

    /**
     * Per-request cache key. Session users are cached per id (see
     * userFromSession); bearer tokens per hash. Anonymous requests share no
     * cache entry and always return null.
     */
    private static function cacheKey(Request $request): ?string
    {
        $token = self::bearerToken($request);

        return $token !== null ? 'bearer:' . self::hash($token) : null;
    }

    /**
     * Revoke the bearer token used by this request (logout for API clients).
     * Session logout is handled separately by Session::logout().
     */
    public static function revoke(Request $request): bool
    {
        $token = self::bearerToken($request);

        if ($token === null) {
            return false;
        }

        return ApiToken::deleteByHash(self::hash($token)) > 0;
    }

    /**
     * Revoke every token of a user across both flavours.
     */
    public static function revokeAllForUser(int $userId): void
    {
        ApiToken::deleteForUser($userId);
    }

    /**
     * Hash used for storage and lookup. A plain SHA-256 (not password_hash)
     * is correct here: tokens are high-entropy random data verified on every
     * request, and the hash must be searchable by equality.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Reset request-scoped state (used by tests and CLI scripts).
     */
    public static function reset(): void
    {
        self::$resolved = [];
        self::$authVia = null;
    }

    private static function formatDateTime(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s');
    }
}
