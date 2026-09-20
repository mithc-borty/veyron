<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal JSON Web Token (HS256) implementation using only native PHP.
 *
 * No external packages: the signature is hash_hmac('sha256', ...) over
 * base64url-encoded JSON segments. Only HS256 tokens issued by this API
 * (matching secret and expiry) are accepted.
 */
final class Jwt
{
    private function __construct()
    {
    }

    /**
     * Build a signed JWT: base64url(header).base64url(payload).base64url(signature).
     *
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload, string $secret): string
    {
        $header = self::base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::base64UrlEncode((string) json_encode($payload));
        $signature = self::base64UrlEncode((string) hash_hmac('sha256', $header . '.' . $body, $secret, true));

        return $header . '.' . $body . '.' . $signature;
    }

    /**
     * Verify the structure, algorithm, signature and time claims.
     *
     * @return array<string, mixed>|null The payload, or null when the token is
     *                                   malformed, incorrectly signed or expired.
     */
    public static function decode(string $jwt, string $secret): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
            return null;
        }

        [$headerSegment, $bodySegment, $signatureSegment] = $parts;

        $header = json_decode(self::base64UrlDecode($headerSegment), true);

        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expected = self::base64UrlEncode(
            (string) hash_hmac('sha256', $headerSegment . '.' . $bodySegment, $secret, true)
        );

        if (!hash_equals($expected, $signatureSegment)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($bodySegment), true);

        if (!is_array($payload)) {
            return null;
        }

        $now = time();

        if (isset($payload['exp']) && (int) $payload['exp'] <= $now) {
            return null;
        }

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            return null;
        }

        return $payload;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $padded = strtr($data, '-_', '+/');
        $remainder = strlen($padded) % 4;

        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? '' : $decoded;
    }
}
