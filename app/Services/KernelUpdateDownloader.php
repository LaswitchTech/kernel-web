<?php

namespace App\Services;

/**
 * Download a kernel update package from a remote URL.
 *
 * The file is saved to a staging directory (configurable, defaults to
 * kernel_root/staging/updates/) so the user can review before applying.
 *
 * Checksum verification is optional — if the remote source provides a
 * checksum (sha256), the downloaded file is verified immediately after
 * download.
 */
class KernelUpdateDownloader
{
    private readonly string $stagingDir;
    private readonly int $timeout;

    public function __construct(?string $stagingDir = null, int $timeout = 30)
    {
        $this->stagingDir = $stagingDir ?? __DIR__ . '/../../staging/updates/';
        $this->timeout = $timeout;
    }

    /**
     * Download the update package.
     *
     * @return object{ success: bool, file?: string, size?: int, checksum_match?: bool, error?: string }
     */
    public function download(string $url, ?string $expectedChecksum = null): object
    {
        // Ensure staging directory exists.
        if (!is_dir($this->stagingDir)) {
            @mkdir($this->stagingDir, 0755, true);
        }

        // Remove any previously staged file.
        $existing = glob($this->stagingDir . 'kernel-update-*.zip');
        foreach ($existing as $file) {
            @unlink($file);
        }

        $filename = $this->stagingDir . 'kernel-update-' . date('Ymd-His') . '.zip';

        $fh = fopen($filename, 'wb');
        if ($fh === false) {
            return (object) ['success' => false, 'error' => 'Cannot create staging file.'];
        }

        if (!function_exists('curl_init')) {
            fclose($fh);
            return (object) ['success' => false, 'error' => 'curl is not available.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: application/zip',
                'User-Agent: Kernel-Web-UpdateChecker',
            ],
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if ($httpCode !== 200 || $curlErr !== '' || !is_file($filename)) {
            @unlink($filename);
            $msg = $curlErr !== '' ? $curlErr : "HTTP $httpCode";
            return (object) ['success' => false, 'error' => 'Download failed: ' . $msg];
        }

        $fileSize = filesize($filename);

        // Verify checksum if provided.
        $checksumMatch = null;
        if ($expectedChecksum !== null) {
            $checksumMatch = $this->verifyChecksum($filename, $expectedChecksum);
            if (!$checksumMatch) {
                @unlink($filename);
                return (object) ['success' => false, 'error' => 'Checksum verification failed.'];
            }
        }

        return (object) ['success' => true, 'file' => $filename, 'size' => $fileSize, 'checksum_match' => $checksumMatch];
    }

    /**
     * Verify the downloaded file against an expected checksum.
     *
     * Supports "sha256:<hash>" or plain hex hash formats.
     */
    private function verifyChecksum(string $filename, string $expected): bool
    {
        $fileHash = hash_file('sha256', $filename);
        if ($fileHash === false) {
            return false;
        }

        // Strip "sha256:" prefix if present.
        $expected = ltrim($expected, 'sha256:');
        return hash_equals($expected, $fileHash);
    }

    /**
     * List staged update packages.
     *
     * @return array<int, object{ file: string, filename: string, size: int, date: string }>
     */
    public function listStaged(): array
    {
        $files = glob($this->stagingDir . 'kernel-update-*.zip');
        if ($files === false || $files === []) {
            return [];
        }

        $result = [];
        foreach ($files as $file) {
            $result[] = (object) [
                'file' => $file,
                'filename' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        // Return newest first.
        return array_reverse($result);
    }

    /**
     * Delete a staged update package.
     */
    public function deleteStaged(string $filename): bool
    {
        $path = $this->stagingDir . $filename;
        if (!is_file($path)) {
            return false;
        }
        return @unlink($path) === true;
    }

    /**
     * Check if a valid staged update exists.
     */
    public function hasStaged(): bool
    {
        return $this->listStaged() !== [];
    }

    /**
     * Get the most recent staged update file.
     */
    public function getStagedFile(): ?string
    {
        $staged = $this->listStaged();
        return $staged !== [] ? $staged[0]['file'] : null;
    }
}
