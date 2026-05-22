<?php

/**
 * Application configuration.
 *
 * Long-lived application identity and baseline settings.
 * Values are read from environment variables set by .env (loaded in
 * public/index.php before this file is required). Defaults cover the case
 * where .env is absent (e.g. during CI or before first install).
 *
 * These values can also be overridden via config/local.php:
 *   return ['app' => ['debug' => false]];
 */

/**
 * Read an env var via Env::get() (which reads from the .env store),
 * falling back to $default when absent.
 */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $val = \App\Core\Env::get($key);
        return $val !== null ? $val : $default;
    }
}

/**
 * Read an env var as a boolean.
 *
 * Truthy strings: 'true', '1', 'yes', 'on' (case-insensitive).
 * Everything else (including absent keys) uses $default.
 */
if (!function_exists('env_bool')) {
    function env_bool(string $key, bool $default = false): bool
    {
        $val = \App\Core\Env::get($key);
        if ($val === null) {
            return $default;
        }
        return in_array(strtolower((string) $val), ['true', '1', 'yes', 'on'], true);
    }
}

return [
    'name'          => (string) env('APP_NAME', 'Kernel-Web'),
    'version'       => (string) env('APP_VERSION', 'dev'),
    'env'           => (string) env('APP_ENV', 'development'),
    'debug'         => env_bool('APP_DEBUG', true),
    'url'           => (string) env('APP_URL', 'http://localhost'),
    'installed'     => env_bool('APP_INSTALLED', false),
    'developer'     => env_bool('APP_DEVELOPER', false),
    'dev_console'   => env_bool('APP_DEV_CONSOLE', true),
];
