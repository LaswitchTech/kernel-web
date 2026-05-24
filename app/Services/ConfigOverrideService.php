<?php

namespace App\Services;

use App\Core\Config;

/**
 * File-backed config override writer.
 *
 * Writes admin-driven settings to config/local.php (not DB, not .env, not base config).
 * Uses dot-notation keys that map to nested arrays:
 *   app.name       → ['app' => ['name' => '...']]
 *   auth.enforced  → ['auth' => ['enforced' => true]]
 *
 * Write path:
 *   1. Read existing local.php (or start with [])
 *   2. Set value at the dot-notation path
 *   3. Write back as valid PHP returning an array
 *   4. Atomic write via temp file + rename
 *   5. Flush Config cache so the new value takes effect
 *
 * Values are normalized:
 *   bool   — stored as PHP boolean (true/false)
 *   int    — stored as integer
 *   string — stored as quoted string (no escaping for simple values)
 */
class ConfigOverrideService
{
    private string $localPath;

    public function __construct(?string $localPath = null)
    {
        $this->localPath = $localPath ?? __DIR__ . '/../../config/local.php';
    }

    /**
     * Read existing local.php config as an array.
     * Returns [] if the file does not exist or is empty.
     */
    public function readLocal(): array
    {
        if (!file_exists($this->localPath)) {
            return [];
        }

        $data = require $this->localPath;
        return is_array($data) ? $data : [];
    }

    /**
     * Write a single value to local.php at the given dot-notation path.
     *
     * @param string $dotKey  e.g. "auth.two_factor.enforced"
     * @param mixed  $value   The normalized value to store
     * @return bool true on success
     */
    public function set(string $dotKey, mixed $value): bool
    {
        $segments = explode('.', $dotKey);

        if (count($segments) < 2) {
            return false;
        }

        // Validate: all segments must be non-empty strings (valid PHP array keys)
        foreach ($segments as $seg) {
            if ($seg === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $seg)) {
                return false;
            }
        }

        $local = $this->readLocal();
        $target = &$local;

        // Walk to the leaf level
        foreach ($segments as $i => $seg) {
            if ($i === count($segments) - 1) {
                $target[$seg] = $value;
            } else {
                if (!isset($target[$seg]) || !is_array($target[$seg])) {
                    $target[$seg] = [];
                }
                $target = &$target[$seg];
            }
        }

        return $this->writeLocal($local);
    }

    /**
     * Write a batch of dot-key → value pairs to local.php.
     * All keys are validated before any write occurs.
     *
     * @return array<string, string>  $key => error message for invalid keys
     */
    public function setBatch(array $pairs): array
    {
        $errors = [];

        foreach ($pairs as $key => $value) {
            if (!$this->validateDotKey($key)) {
                $errors[$key] = 'Invalid config key: must be at least two dot-separated segments (e.g. app.name).';
                continue;
            }

            $segments = explode('.', $key);
            if (count($segments) < 2) {
                $errors[$key] = 'Config key requires at least two dot-separated segments.';
                continue;
            }
        }

        if (!empty($errors)) {
            return $errors;
        }

        $local = $this->readLocal();

        foreach ($pairs as $key => $value) {
            $segments = explode('.', $key);
            $target = &$local;

            foreach ($segments as $i => $seg) {
                if ($i === count($segments) - 1) {
                    $target[$seg] = $value;
                } else {
                    if (!isset($target[$seg]) || !is_array($target[$seg])) {
                        $target[$seg] = [];
                    }
                    $target = &$target[$seg];
                }
            }
        }

        $this->writeLocal($local);
        return [];
    }

    /**
     * Normalize a form value to a typed PHP value.
     *
     * Checkbox inputs send '1'/'0'. This normalizes them to bool.
     * String values are returned as-is. Integer 1/0 are normalized to bool.
     */
    public static function normalizeFormValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            if ($value === '1') {
                return true;
            }
            if ($value === '0') {
                return false;
            }
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        return $value;
    }

    // ------ private ------

    /**
     * Write local config as valid PHP and flush the cache.
     * Uses atomic rename to prevent corruption.
     */
    private function writeLocal(array $data): bool
    {
        $content = $this->formatPhp($data);
        $tmpPath = $this->localPath . '.tmp.' . getmypid();

        if (@file_put_contents($tmpPath, $content) === false) {
            @unlink($tmpPath);
            return false;
        }

        if (rename($tmpPath, $this->localPath)) {
            Config::flush();
            return true;
        }

        @unlink($tmpPath);
        return false;
    }

    /**
     * Validate that a string is a valid dot-separated PHP array key path.
     */
    private function validateDotKey(string $key): bool
    {
        if ($key === '' || str_contains($key, '..')) {
            return false;
        }
        $segments = explode('.', $key);
        foreach ($segments as $seg) {
            if ($seg === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $seg)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Format an array as PHP returning the data.
     * Produces clean, readable PHP code.
     */
    private function formatPhp(array $data): string
    {
        $lines = ["<?php\n\n", "return [\n"];

        $this->formatArray($data, $lines, 1);

        $lines[] = "];";
        return implode("\n", $lines);
    }

    /**
     * Recursively format nested arrays.
     */
    private function formatArray(array $data, array &$lines, int $depth): void
    {
        $indent = str_repeat('    ', $depth);

        foreach ($data as $key => $value) {
            $formattedKey = $this->formatKey($key);

            if (is_array($value)) {
                $lines[] = "{$indent}{$formattedKey} => [";
                $this->formatArray($value, $lines, $depth + 1);
                $lines[] = "{$indent}],";
            } elseif (is_bool($value)) {
                $lines[] = "{$indent}{$formattedKey} => " . ($value ? 'true' : 'false') . ',';
            } elseif (is_int($value) || is_float($value)) {
                $lines[] = "{$indent}{$formattedKey} => {$value},";
            } elseif (is_null($value)) {
                $lines[] = "{$indent}{$formattedKey} => null,";
            } else {
                $escaped = addcslashes((string) $value, "\\\"'");
                $lines[] = "{$indent}{$formattedKey} => '{$escaped}',";
            }
        }
    }

    /**
     * Format a single key for PHP output.
     * Always quoted — bare identifiers in arrow notation are constants in PHP.
     */
    private function formatKey(string $key): string
    {
        return "'{$key}'";
    }
}
