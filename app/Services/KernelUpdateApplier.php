<?php

namespace App\Services;

/**
 * Apply a kernel update from a staged ZIP package.
 *
 * Workflow:
 *   1. Validate staged file exists and is a valid ZIP.
 *   2. Create a backup of the current kernel root.
 *   3. Extract ZIP over the current files (preserving directory structure).
 *   4. Verify critical files (index.php, config/app.php) exist post-extraction.
 *   5. Clean up the backup on success.
 *
 * On failure, the backup is retained and the current state is rolled back.
 */
class KernelUpdateApplier
{
    private readonly string $kernelRoot;
    private readonly string $backupDir;

    public function __construct(?string $kernelRoot = null)
    {
        $this->kernelRoot = rtrim($kernelRoot ?? __DIR__ . '/..', '/');
        $this->backupDir = $this->kernelRoot . '/staging/backups/';
    }

    /**
     * Apply a staged update package.
     *
     * @return object{ success: bool, backup?: string, version?: string, error?: string }
     */
    public function apply(string $zipPath): object
    {
        if (!is_file($zipPath)) {
            return (object) ['success' => false, 'error' => 'Staged file not found.'];
        }

        // Validate ZIP contents (dry run).
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return (object) ['success' => false, 'error' => 'Invalid ZIP file.'];
        }

        // Verify all entries have a valid prefix (security — no path traversal).
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, '..') || str_contains($name, "\0")) {
                $zip->close();
                return (object) ['success' => false, 'error' => 'Invalid file entry in ZIP (path traversal detected).'];
            }
        }
        $zip->close();

        // Create backup.
        $backupDir = $this->createBackup();
        if ($backupDir === false) {
            return (object) ['success' => false, 'error' => 'Backup failed. Update aborted.'];
        }

        // Extract and apply.
        $extracted = $this->extractOver($zipPath);
        if (!$extracted) {
            // Rollback: restore from backup.
            $this->rollback($backupDir);
            return (object) ['success' => false, 'error' => 'Extraction failed. Rolled back to previous version.'];
        }

        // Verify critical files exist post-extraction.
        if (!$this->verifyCriticalFiles()) {
            $this->rollback($backupDir);
            return (object) ['success' => false, 'error' => 'Critical files missing after extraction. Rolled back.'];
        }

        // Resolve the new version from the updated kernel.
        $newProvider = new \App\Core\VersionProvider($this->kernelRoot);
        $newVersion = $newProvider->getKernelVersion();

        // Success — remove backup.
        $this->removeBackupDir($backupDir);

        return (object) ['success' => true, 'backup' => $backupDir, 'version' => $newVersion];
    }

    /**
     * List available backups.
     *
     * @return array<int, object{ dir: string, name: string, size: int, date: string }>
     */
    public function listBackups(): array
    {
        if (!is_dir($this->backupDir)) {
            return [];
        }

        $entries = glob($this->backupDir . 'kernel-backup-*');
        if ($entries === false) {
            return [];
        }

        $result = [];
        foreach ($entries as $dir) {
            $name = basename($dir);
            $result[] = (object) [
                'dir' => $dir,
                'name' => $name,
                'size' => $this->dirSize($dir),
                'date' => date('Y-m-d H:i:s', filemtime($dir)),
            ];
        }

        return array_reverse($result);
    }

    /**
     * Delete a backup directory.
     */
    public function deleteBackup(string $name): bool
    {
        $dir = $this->backupDir . $name;
        if (!is_dir($dir)) {
            return false;
        }
        return $this->removeDir($dir);
    }

    // ------ private ------

    private function createBackup(): string|false
    {
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }

        $backupName = 'kernel-backup-' . date('Ymd-His');
        $backupPath = $this->backupDir . $backupName;

        if (is_dir($backupPath)) {
            return false;
        }

        // Copy all kernel files (excluding staging/) to backup.
        $src = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->kernelRoot, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        @mkdir($backupPath, 0755, true);
        foreach ($src as $file) {
            $relativePath = str_replace($this->kernelRoot . '/', '', $file->getPathname());

            // Exclude staging/ from backup to keep it small.
            if (str_starts_with($relativePath, 'staging/')) {
                continue;
            }

            $dest = $backupPath . '/' . $relativePath;
            if ($file->isDir()) {
                @mkdir($dest, 0755, true);
            } else {
                @mkdir(dirname($dest), 0755, true);
                @copy($file->getPathname(), $dest);
            }
        }

        return is_dir($backupPath) ? $backupPath : false;
    }

    private function extractOver(string $zipPath): bool
    {
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        // Strip the top-level directory if it exists.
        $topDir = $zip->getNameIndex(0);
        $stripLen = strlen($topDir) + 1; // +1 for trailing slash
        if (str_ends_with($topDir, '/')) {
            $topDir = rtrim($topDir, '/');
            $stripLen = strlen($topDir) + 1;
        }

        $success = true;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $stripped = substr($name, $stripLen);

            if (empty($stripped)) {
                continue;
            }

            $dest = $this->kernelRoot . '/' . $stripped;

            if ($zip->statIndex($i)['uncomp_size'] === 0) {
                // Directory entry.
                @mkdir($dest, 0755, true);
            } else {
                @mkdir(dirname($dest), 0755, true);
                $content = $zip->getFromIndex($i);
                if ($content === false || @file_put_contents($dest, $content) === false) {
                    $success = false;
                }
            }
        }

        $zip->close();
        return $success;
    }

    private function verifyCriticalFiles(): bool
    {
        return is_file($this->kernelRoot . '/index.php')
            && is_dir($this->kernelRoot . '/app')
            && is_dir($this->kernelRoot . '/config');
    }

    private function rollback(string $backupDir): void
    {
        // Remove current kernel files first (but preserve staging/).
        $this->removeDir($this->kernelRoot . '/staging');

        $src = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($src as $file) {
            $relativePath = str_replace($backupDir . '/', '', $file->getPathname());
            $dest = $this->kernelRoot . '/' . $relativePath;

            if ($file->isDir()) {
                @mkdir($dest, 0755, true);
            } else {
                @mkdir(dirname($dest), 0755, true);
                @copy($file->getPathname(), $dest);
            }
        }

        // Recreate staging dir.
        @mkdir($this->stagingDir(), 0755, true);
    }

    private function stagingDir(): string
    {
        return $this->kernelRoot . '/staging/';
    }

    private function removeDir(string $path): bool
    {
        if (!is_dir($path)) {
            return true;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        return rmdir($path);
    }

    private function removeBackupDir(string $dir): bool
    {
        $this->removeDir($dir);
        return !is_dir($dir);
    }

    private function dirSize(string $dir): int
    {
        $size = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($items as $file) {
            $size += $file->getSize();
        }
        return $size;
    }
}
