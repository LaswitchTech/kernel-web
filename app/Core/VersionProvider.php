<?php

namespace App\Core;

/**
 * Resolves version information for the kernel, application, and update status.
 *
 * Responsibilities:
 *   - Resolve kernel version (composer.json → VERSION file → "dev")
 *   - Resolve application name/version from config/app.php
 *   - Provide a unified getVersions() for admin overview display
 *
 * This service is registered in public/index.php after config is loaded.
 */
class VersionProvider
{
    private readonly string $kernelRoot;

    public function __construct(string $kernelRoot)
    {
        $this->kernelRoot = rtrim($kernelRoot, '/');
    }

    /**
     * Resolve the kernel version string.
     *
     * Priority:
     *   1. VERSION file at repository root
     *   2. composer.json → version field
     *   3. "dev" (unknown/unversioned)
     */
    public function getKernelVersion(): string
    {
        // Fallback: VERSION file
        $versionFile = $this->kernelRoot . '/VERSION';
        if (is_file($versionFile)) {
            $v = trim((string) file_get_contents($versionFile));
            if ($v !== '') {
                return $v;
            }
        }

        // Primary: composer.json
        $composerFile = $this->kernelRoot . '/composer.json';
        if (is_file($composerFile)) {
            $data = json_decode((string) file_get_contents($composerFile), true);
            if (is_array($data) && isset($data['version']) && $data['version'] !== '') {
                return (string) $data['version'];
            }
        }

        return 'dev';
    }

    /**
     * Resolve the kernel name.
     *
     * Priority:
     *   1. composer.json → name field
     *   2. "Kernel-Web" (default)
     */
    public function getKernelName(): string
    {
        $composerFile = $this->kernelRoot . '/composer.json';
        if (is_file($composerFile)) {
            $data = json_decode((string) file_get_contents($composerFile), true);
            if (is_array($data) && isset($data['name']) && $data['name'] !== '') {
                return (string) $data['name'];
            }
        }

        return 'Kernel-Web';
    }

    /**
     * Resolve the application name from config.
     *
     * @param array<string, mixed> $appConfig Loaded config/app.php array
     */
    public function getApplicationName(array $appConfig): string
    {
        $name = $appConfig['name'] ?? $appConfig['app_name'] ?? 'Kernel-Web';
        return is_string($name) && $name !== '' ? $name : 'Kernel-Web';
    }

    /**
     * Resolve the application version from config.
     *
     * @param array<string, mixed> $appConfig Loaded config/app.php array
     */
    public function getApplicationVersion(array $appConfig): string
    {
        $version = $appConfig['version'] ?? $appConfig['app_version'] ?? 'dev';
        return is_string($version) && $version !== '' ? $version : 'dev';
    }

    /**
     * Get all version information for admin overview display.
     *
     * @param array<string, mixed> $appConfig Loaded config/app.php array
     * @return array{
     *       kernel: array{name: string, version: string},
     *       application: array{name: string, version: string},
     *       updates: array{configured: bool, kernel_available: null|string, application_available: null|string, extensions_available: null|int}
     *   }
     */
    public function getVersions(array $appConfig): array
    {
        $kernelName = $this->getKernelName();
        $kernelVersion = $this->getKernelVersion();
        $appName = $this->getApplicationName($appConfig);
        $appVersion = $this->getApplicationVersion($appConfig);

        return [
            'kernel' => [
                'name' => $kernelName,
                'version' => $kernelVersion,
            ],
            'application' => [
                'name' => $appName,
                'version' => $appVersion,
            ],
            'updates' => [
                'configured' => false,
                'kernel_available' => null,
                'application_available' => null,
                'extensions_available' => null,
            ],
        ];
    }
}
