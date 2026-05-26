<?php

namespace App\Contracts;

/**
 * Interface for services that write configuration values.
 *
 * Implementations include:
 *   - ConfigOverrideService: writes to config/local.php
 *
 * Used by SettingsRegistry and plugin settings to persist
 * non-sensitive plugin configuration independently of the
 * database-backed SystemSettingService.
 */
interface ConfigWriterInterface
{
    /**
     * Write a single value at the given dot-notation path.
     *
     * @return bool true on success
     */
    public function set(string $key, mixed $value): bool;

    /**
     * Write a batch of dot-key → value pairs.
     *
     * @return array<string, string>  $key => error message for invalid keys
     */
    public function setBatch(array $pairs): array;
}
