<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PSR-4 autoloader.
 *
 * Registration example (index.php):
 *
 *     Autoloader::register('App\\', BASE_PATH . '/app');
 *
 * which maps App\Core\Router to app/Core/Router.php.
 */
final class Autoloader
{
    /**
     * Whether the autoload callback has been registered with SPL.
     */
    private static bool $registered = false;

    /**
     * Namespace prefix => base directory (with trailing slash).
     *
     * @var array<string, string>
     */
    private static array $prefixes = [];

    /**
     * Register a namespace prefix. Safe to call more than once.
     */
    public static function register(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/') . '/';

        self::$prefixes[$prefix] = $baseDir;

        if (!self::$registered) {
            spl_autoload_register([self::class, 'load']);
            self::$registered = true;
        }
    }

    /**
     * Resolve a fully qualified class name to a file and include it.
     */
    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
}