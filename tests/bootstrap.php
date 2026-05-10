<?php

/**
 * Kernel autoloader bootstrap for tests.
 *
 * Mirrors the autoloader from public/index.php so test files can use
 * App\Core\... classes without Composer.
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $base   = __DIR__ . '/../app/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $fileName = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($fileName)) {
        require $fileName;
    }
});
