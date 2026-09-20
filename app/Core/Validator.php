<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight input validation - no validation framework.
 *
 *     $errors = Validator::validate($request->body(), [
 *         'name'     => 'required|string|min:2|max:80',
 *         'email'    => 'required|email',
 *         'password' => 'required|string|min:8|max:72',
 *         'role'     => 'nullable|in:admin,user',
 *     ]);
 *
 * Returns field => first error message; an empty array means the input is valid.
 *
 * Supported rules:
 *   required, nullable, string, integer, numeric, boolean, array, email, url,
 *   date, regex:pattern, in:a,b, not_in:a,b, min:n, max:n,
 *   same:field, different:field, confirmed,
 *   unique:table,column[,ignoreId[,idColumn]], exists:table,column
 *
 * Unknown or malformed rules fail closed: they produce a validation error and
 * are logged, so a typo can never silently disable validation.
 *
 * Database-backed rules (unique, exists) use App\Core\Database prepared
 * statements; identifiers are restricted to [A-Za-z0-9_] and quoted.
 */
final class Validator
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @return array<string, string>
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = self::parseRules((string) $ruleString);
            $value = $data[$field] ?? null;
            $empty = $value === null || $value === '';

            if ($empty) {
                // A missing or empty value only fails when it is required.
                if (in_array('required', $fieldRules, true)) {
                    $errors[$field] = sprintf('The %s field is required.', self::label($field));
                }

                continue;
            }

            foreach ($fieldRules as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                [$name, $parameter] = self::split($rule);
                $problem = self::check($name, $parameter, $value, $field, $data);

                if ($problem !== null) {
                    $errors[$field] = sprintf('The %s field %s.', self::label($field), $problem);
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private static function parseRules(string $ruleString): array
    {
        return array_values(array_filter(
            array_map('trim', explode('|', $ruleString)),
            static fn (string $rule): bool => $rule !== ''
        ));
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function split(string $rule): array
    {
        if (!str_contains($rule, ':')) {
            return [strtolower($rule), null];
        }

        [$name, $parameter] = explode(':', $rule, 2);

        return [strtolower($name), $parameter];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function check(string $rule, ?string $parameter, mixed $value, string $field, array $data): ?string
    {
        return match ($rule) {
            'string' => is_string($value) ? null : 'must be a string',
            'integer' => self::isInteger($value) ? null : 'must be an integer',
            'numeric' => is_numeric($value) ? null : 'must be a number',
            'boolean' => self::isBoolean($value) ? null : 'must be true or false',
            'array' => is_array($value) ? null : 'must be an array',
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null
                : 'must be a valid email address',
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false
                ? null
                : 'must be a valid URL',
            'date' => self::isDate($value) ? null : 'must be a valid date (Y-m-d)',
            'regex' => self::matches($parameter, $value, $rule),
            'min' => self::bound($parameter, $value, true),
            'max' => self::bound($parameter, $value, false),
            'in' => self::oneOf($parameter, $value),
            'not_in' => self::notOneOf($parameter, $value),
            'same' => self::same($data, $field, $parameter),
            'different' => self::different($data, $field, $parameter),
            'confirmed' => self::confirmed($data, $field),
            'unique' => self::unique($parameter, $value, $rule),
            'exists' => self::exists($parameter, $value, $rule),
            // Fail closed: a typo must never silently disable validation.
            default => self::unsupported($rule, 'unknown rule on field ' . $field),
        };
    }

    /**
     * Confirms the field against "<field>_confirmation" (e.g. password).
     *
     * @param array<string, mixed> $data
     */
    private static function confirmed(array $data, string $field): ?string
    {
        $confirmation = $data[$field . '_confirmation'] ?? null;

        if (!is_scalar($confirmation) || $confirmation === '' || (string) ($data[$field] ?? '') !== (string) $confirmation) {
            return 'must match the confirmation field';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function same(array $data, string $field, ?string $other): ?string
    {
        if ($other === null || !array_key_exists($other, $data)) {
            return self::unsupported('same', 'missing field for ' . $field);
        }

        return (string) ($data[$field] ?? '') === (string) $data[$other]
            ? null
            : sprintf('must match the %s field', self::label($other));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function different(array $data, string $field, ?string $other): ?string
    {
        if ($other === null || !array_key_exists($other, $data)) {
            return self::unsupported('different', 'missing field for ' . $field);
        }

        return (string) ($data[$field] ?? '') !== (string) $data[$other]
            ? null
            : sprintf('must be different from the %s field', self::label($other));
    }

    /**
     * regex rule; the pattern is written without delimiters: regex:^[a-z]+$
     */
    private static function matches(?string $pattern, mixed $value, string $rule): ?string
    {
        if ($pattern === null || !is_scalar($value)) {
            return self::unsupported($rule, 'missing pattern');
        }

        $result = @preg_match('/' . str_replace('/', '\/', $pattern) . '/', (string) $value);

        if ($result === false) {
            return self::unsupported($rule, 'invalid pattern');
        }

        return $result === 1 ? null : 'has an invalid format';
    }

    private static function notOneOf(?string $parameter, mixed $value): ?string
    {
        if ($parameter === null) {
            return self::unsupported('not_in', 'missing list');
        }

        $forbidden = array_map('trim', explode(',', $parameter));

        return in_array((string) $value, $forbidden, true)
            ? 'must not be one of: ' . implode(', ', $forbidden)
            : null;
    }

    private static function isDate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /**
     * unique:table,column[,ignoreId[,idColumn]]
     */
    private static function unique(?string $parameter, mixed $value, string $rule): ?string
    {
        if (!is_scalar($value)) {
            return self::unsupported($rule, 'non scalar value');
        }

        $config = self::tableRule($parameter, $rule);

        if (is_string($config)) {
            return $config;
        }

        [$table, $column, $ignoreId, $idColumn] = $config;

        $sql = sprintf('SELECT COUNT(*) AS c FROM `%s` WHERE `%s` = ?', $table, $column);
        $params = [$value];

        if ($ignoreId !== null) {
            $sql .= sprintf(' AND `%s` <> ?', $idColumn);
            $params[] = $ignoreId;
        }

        $count = self::count($sql, $params, $rule);

        if ($count === null) {
            return 'could not be validated';
        }

        return $count === 0 ? null : 'has already been taken';
    }

    /**
     * exists:table,column
     */
    private static function exists(?string $parameter, mixed $value, string $rule): ?string
    {
        if (!is_scalar($value)) {
            return self::unsupported($rule, 'non scalar value');
        }

        $config = self::tableRule($parameter, $rule);

        if (is_string($config)) {
            return $config;
        }

        [$table, $column] = $config;

        $count = self::count(
            sprintf('SELECT COUNT(*) AS c FROM `%s` WHERE `%s` = ?', $table, $column),
            [$value],
            $rule
        );

        if ($count === null) {
            return 'could not be validated';
        }

        return $count > 0 ? null : 'does not exist';
    }

    /**
     * @param array<int, mixed> $params
     */
    private static function count(string $sql, array $params, string $rule): ?int
    {
        try {
            $row = Database::selectOne($sql, $params);
        } catch (\Throwable $exception) {
            // Misconfigured rule (unknown table/column) must not become a 500.
            Logger::error('Validation query failed', ['rule' => $rule, 'exception' => $exception]);

            return null;
        }

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Parse "table,column[,ignoreId[,idColumn]]" and guard the identifiers.
     *
     * @return array{0: string, 1: string, 2: int|null, 3: string}|string
     */
    private static function tableRule(?string $parameter, string $rule): array|string
    {
        $parts = $parameter === null ? [] : array_map('trim', explode(',', $parameter));
        $table = $parts[0] ?? '';
        $column = $parts[1] ?? '';
        $idColumn = ($parts[3] ?? '') !== '' ? $parts[3] : 'id';
        $ignoreId = isset($parts[2]) && ctype_digit((string) $parts[2]) ? (int) $parts[2] : null;

        foreach ([$table, $column, $idColumn] as $identifier) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
                return self::unsupported($rule, 'invalid identifier');
            }
        }

        return [$table, $column, $ignoreId, $idColumn];
    }

    /**
     * Never fail silently, never leak rule internals to the client.
     */
    private static function unsupported(string $rule, string $detail = ''): string
    {
        Logger::warning('Unsupported validation rule', ['rule' => $rule, 'detail' => $detail]);

        return 'has an unsupported validation rule';
    }

    private static function bound(?string $parameter, mixed $value, bool $isMinimum): ?string
    {
        if ($parameter === null || !is_numeric($parameter)) {
            return null;
        }

        $limit = (int) $parameter;

        if (is_string($value)) {
            $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

            if ($isMinimum && $length < $limit) {
                return sprintf('must be at least %d characters', $limit);
            }

            if (!$isMinimum && $length > $limit) {
                return sprintf('must not be greater than %d characters', $limit);
            }

            return null;
        }

        if (is_int($value) || is_float($value)) {
            if ($isMinimum && $value < $limit) {
                return sprintf('must be at least %d', $limit);
            }

            if (!$isMinimum && $value > $limit) {
                return sprintf('must not be greater than %d', $limit);
            }
        }

        return null;
    }

    private static function oneOf(?string $parameter, mixed $value): ?string
    {
        if ($parameter === null) {
            return null;
        }

        $allowed = array_map('trim', explode(',', $parameter));

        return in_array((string) $value, $allowed, true)
            ? null
            : 'must be one of: ' . implode(', ', $allowed);
    }

    private static function isInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        return is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private static function isBoolean(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1'], true);
    }

    /**
     * Human readable field name for error messages.
     */
    private static function label(string $field): string
    {
        return str_replace(['_', '.'], ' ', $field);
    }
}