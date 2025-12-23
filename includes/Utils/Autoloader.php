<?php

declare(strict_types=1);

namespace StarRate\Utils;

final class Autoloader {
    private const NAMESPACE_PREFIX = 'StarRate\\';
    private const BASE_DIR = STAR_RATE_PLUGIN_DIR . 'includes/';

    public static function register(): void {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void {
        if (strpos($class, self::NAMESPACE_PREFIX) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen(self::NAMESPACE_PREFIX));
        $file = self::BASE_DIR . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
}
