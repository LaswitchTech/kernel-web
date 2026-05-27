<?php

namespace App\Services;

/**
 * Check for kernel updates from a remote source.
 *
 * Remote source must return a JSON document with:
 *   {
 *     "version": "1.1.0",
 *     "download_url": "https://...",
 *     "checksum": "sha256:...",
 *     "notes": "Optional release notes"
 *   }
 *
 * If download_url is absent, the version is used as the URL.
 * If checksum is absent, no checksum verification is performed during download.
 */
class KernelUpdateChecker
{
    private readonly ?string $sourceUrl;
    private readonly int $timeout;

    public function __construct(?string $sourceUrl = null, int $timeout = 10)
    {
        $this->sourceUrl = $sourceUrl;
        $this->timeout = $timeout;
    }

    /**
     * Check for a kernel update.
     *
     * @return object{
     *     has_update: bool,
     *     current_version: string,
     *     latest_version: null|string,
     *     download_url: null|string,
     *     checksum: null|string,
     *     notes: null|string,
     *     error: null|string
     * }
     */
    public function check(string $currentVersion): object
    {
        if ($this->sourceUrl === null) {
            return (object) [
                'has_update' => false,
                'current_version' => $currentVersion,
                'latest_version' => null,
                'download_url' => null,
                'checksum' => null,
                'notes' => null,
                'error' => 'Update check not configured.',
            ];
        }

        $response = $this->fetchUpdateInfo();
        if ($response === null) {
            return (object) [
                'has_update' => false,
                'current_version' => $currentVersion,
                'latest_version' => null,
                'download_url' => null,
                'checksum' => null,
                'notes' => null,
                'error' => 'Failed to reach update source.',
            ];
        }

        $latestVersion = $response->version ?? null;
        if ($latestVersion === null || $latestVersion === '') {
            return (object) [
                'has_update' => false,
                'current_version' => $currentVersion,
                'latest_version' => null,
                'download_url' => null,
                'checksum' => null,
                'notes' => null,
                'error' => 'Update source returned no version.',
            ];
        }

        $hasUpdate = version_compare($currentVersion, $latestVersion) < 0;

        return (object) [
            'has_update' => $hasUpdate,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'download_url' => $response->download_url ?? null,
            'checksum' => $response->checksum ?? null,
            'notes' => $response->notes ?? null,
            'error' => null,
        ];
    }

    /**
     * Fetch the update info JSON from the remote source.
     *
     * @return object|null Parsed JSON or null on failure.
     */
    private function fetchUpdateInfo(): ?object
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($this->sourceUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Kernel-Web-UpdateChecker',
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            return null;
        }

        $data = @json_decode($body);
        return is_object($data) ? $data : null;
    }
}
