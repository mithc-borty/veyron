<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;
use PDOException;
use RuntimeException;

/**
 * Authentication business logic: register, login, logout and current user.
 *
 * The controller stays thin: it receives a result array and turns it into an
 * HTTP response. No password, hash or raw token ever leaves this service - a
 * new token is returned exactly once, in the login response.
 */
final class AuthService
{
    /**
     * Login failure reasons (mapped to HTTP status codes by the controller).
     */
    public const FAILURE_CREDENTIALS = 'credentials';
    public const FAILURE_INACTIVE = 'inactive';

    /**
     * Bcrypt hash of a throwaway value. It is verified when the email is
     * unknown so response timing does not reveal which emails exist.
     */
    private const DUMMY_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    /**
     * Verify credentials WITHOUT establishing a session or issuing a token.
     * Shared by the API login (which adds session + token) and the web form
     * login (which adds a session only).
     *
     * @param array<string, mixed> $input Raw input (JSON body or form fields).
     * @return array{errors: array<string, string>, failure: string|null, user: array<string, mixed>|null}
     */
    public function attempt(array $input): array
    {
        $email = self::normalizeEmail((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $errors = Validator::validate(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|string|email', 'password' => 'required|string']
        );

        if ($errors !== []) {
            return ['errors' => $errors, 'failure' => null, 'user' => null];
        }

        $user = User::findByEmail($email);
        $passwordMatches = password_verify($password, (string) ($user['password'] ?? self::DUMMY_HASH));

        // Same response for unknown email and wrong password.
        if ($user === null || !$passwordMatches) {
            return ['errors' => [], 'failure' => self::FAILURE_CREDENTIALS, 'user' => null];
        }

        if (($user['status'] ?? null) !== User::STATUS_ACTIVE) {
            return ['errors' => [], 'failure' => self::FAILURE_INACTIVE, 'user' => null];
        }

        return ['errors' => [], 'failure' => null, 'user' => $user];
    }

    /**
     * Verify credentials and establish authentication in all supported modes:
     * the native session (browser) plus a returned bearer token (JWT when
     * JWT_SECRET is configured, otherwise a legacy opaque token).
     *
     * @param array<string, mixed> $input Raw request body.
     * @return array{errors: array<string, string>, failure: string|null, token: string|null, token_type: string|null, expires_at: string|null, csrf_token: string|null, user: array<string, mixed>|null}
     */
    public function login(array $input): array
    {
        $attempt = $this->attempt($input);

        if ($attempt['errors'] !== [] || $attempt['failure'] !== null) {
            return self::failure($attempt['errors'], $attempt['failure']);
        }

        $user = (array) $attempt['user'];

        // Browser mode: issue the session cookie (only reachable through
        // HTTP - Session::start() bails out under CLI, so tests are safe).
        Session::login((int) $user['id']);

        // API mode: JWT is preferred; fall back to the legacy opaque token
        // when no JWT_SECRET is configured.
        $issued = Auth::issueJwt($user);
        $tokenType = $issued !== null ? 'Bearer' : null;

        if ($issued === null) {
            $issued = Auth::issueToken($user);
        }

        return [
            'errors' => [],
            'failure' => null,
            'token' => $issued['token'],
            'token_type' => $tokenType ?? 'Bearer',
            'expires_at' => $issued['expires_at'],
            'csrf_token' => Session::csrfToken(),
            'user' => User::toPublic($user),
        ];
    }

    /**
     * Forget the session of the current request. Returns true when a session
     * user was logged out.
     */
    public function logoutSession(): bool
    {
        return Session::logout();
    }

    /**
     * Public representation of the authenticated user.
     *
     * @param array<string, mixed> $user Authenticated user row (contains the password hash).
     * @return array<string, mixed>
     */
    public function currentUser(array $user): array
    {
        return User::toPublic($user);
    }

    /**
     * CSRF token bound to the current session (empty when no session exists).
     */
    public function sessionCsrfToken(): string
    {
        return Session::hasUser() ? Session::csrfToken() : '';
    }

    /**
     * @param array<string, string> $errors
     * @return array{errors: array<string, string>, failure: string|null, token: string|null, token_type: string|null, expires_at: string|null, csrf_token: string|null, user: array<string, mixed>|null}
     */
    private static function failure(array $errors = [], ?string $failure = null): array
    {
        return [
            'errors' => $errors,
            'failure' => $failure,
            'token' => null,
            'token_type' => null,
            'expires_at' => null,
            'csrf_token' => null,
            'user' => null,
        ];
    }

    /**
     * Validation rules used by registration (and later by user creation).
     *
     * @return array<string, string>
     */
    public static function registerRules(): array
    {
        return [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|string|email|max:255|unique:' . User::TABLE . ',email',
            'password' => 'required|string|min:8|max:72|confirmed',
            'password_confirmation' => 'required|string',
        ];
    }

    /**
     * Validate and store a new user.
     *
     * @param array<string, mixed> $input Raw request body.
     * @return array{errors: array<string, string>, user: array<string, mixed>|null}
     */
    public function register(array $input): array
    {
        // Normalise first so "  Test@Example.COM  " is validated and stored as
        // "test@example.com" (the unique check must see the stored shape).
        $input['name'] = trim((string) ($input['name'] ?? ''));
        $input['email'] = self::normalizeEmail((string) ($input['email'] ?? ''));

        $errors = Validator::validate($input, self::registerRules());

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
                // Never taken from the request: no privilege escalation and no
                // self-deactivation through mass assignment.
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
            ]);
        } catch (PDOException $exception) {
            // Race condition: another request inserted the same email between
            // the unique validation and this insert.
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return [
                    'errors' => ['email' => 'The email field has already been taken.'],
                    'user' => null,
                ];
            }

            throw $exception;
        }

        $user = User::find($id);

        if ($user === null) {
            throw new RuntimeException('The created user could not be read back.');
        }

        return ['errors' => [], 'user' => User::toPublic($user)];
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}