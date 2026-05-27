<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\ConfigOverrideService;
use App\Services\KernelUpdateChecker;
use App\Services\KernelUpdateDownloader;
use App\Services\KernelUpdateApplier;

/**
 * Kernel update management.
 *
 * Routes:
 *   GET  /admin/updates         → index()    Update management page
 *   POST /admin/updates/download → download() Download latest update
 *   POST /admin/updates/apply     → apply()    Apply staged update
 *   GET  /admin/updates/staging   → staging()  List staged/backup files
 *   POST /admin/updates/rollback  → rollback() Restore from backup
 *
 * All routes protected by ['WebAuth', 'WebPermission:admin'].
 */
class KernelUpdatesController extends Controller
{
    // ------ GET /admin/updates ---

    public function index(array $params = []): void
    {
        [$viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $versionProvider = $this->container->get('version_provider');
        $kernelVersion = $versionProvider->getKernelVersion();

        // Read update source URL from config.
        $localConfig = (new ConfigOverrideService())->readLocal();
        $updateUrl = ($localConfig['updates']['kernel'] ?? null) ?? ($localConfig['kernel']['url'] ?? null);

        // Load staged updates.
        $downloader = new KernelUpdateDownloader();
        $staged = $downloader->listStaged();

        // Load backups.
        $applier = new KernelUpdateApplier();
        $backups = $applier->listBackups();

        // Status message from flash or session.
        $flash = $this->popFlash();

        // Check for pending update.
        $hasPendingUpdate = $downloader->hasStaged();

        $pageTitle = 'Kernel Updates';
        $activeSection = '/admin/updates';
        $errors = [];

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Kernel Updates', 'url' => null],
        ];

        ob_start();
        require $viewsPath . '/admin/kernel_updates.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    // ------ GET /admin/updates/staging ---

    public function staging(array $params = []): void
    {
        $downloader = new KernelUpdateDownloader();
        $applier = new KernelUpdateApplier();

        $staged = $downloader->listStaged();
        $backups = $applier->listBackups();

        $this->jsonResponse(200, [
            'staged' => $staged,
            'backups' => $backups,
            'has_staged' => $downloader->hasStaged(),
            'has_backup' => $backups !== [],
        ]);
    }

    // ------ POST /admin/updates/download ---

    public function download(array $params = []): void
    {
        [$appName, $displayName, $permissions] = $this->ctx();

        $config = $this->container->get('config');
        $localConfig = (new ConfigOverrideService())->readLocal();
        $updateUrl = ($localConfig['updates']['kernel'] ?? null) ?? ($localConfig['kernel']['url'] ?? null);

        if ($updateUrl === null) {
            $this->jsonResponse(400, ['ok' => false, 'error' => 'Update source not configured. Set kernel URL in /admin/settings.']);
            return;
        }

        // First check for the latest version to get download URL + checksum.
        $checker = new KernelUpdateChecker($updateUrl);
        $versionProvider = $this->container->get('version_provider');
        $currentVersion = $versionProvider->getKernelVersion();
        $checkResult = $checker->check($currentVersion);

        if ($checkResult->error !== null) {
            $this->jsonResponse(500, ['ok' => false, 'error' => $checkResult->error]);
            return;
        }

        if (!$checkResult->has_update) {
            $this->jsonResponse(200, ['ok' => true, 'message' => 'Already up to date.']);
            return;
        }

        $downloadUrl = $checkResult->download_url ?? $checkResult->latest_version;
        if ($downloadUrl === null) {
            $this->jsonResponse(400, ['ok' => false, 'error' => 'No download URL provided by update source.']);
            return;
        }

        $downloader = new KernelUpdateDownloader();
        $result = $downloader->download($downloadUrl, $checkResult->checksum);

        if (!$result->success) {
            $this->jsonResponse(500, ['ok' => false, 'error' => $result->error]);
            return;
        }

        $this->jsonResponse(200, [
            'ok' => true,
            'message' => "Update v{$checkResult->latest_version} downloaded.",
            'version' => $checkResult->latest_version,
            'checksum_match' => $result->checksum_match,
            'size' => $result->size,
        ]);
    }

    // ------ POST /admin/updates/apply ---

    public function apply(array $params = []): void
    {
        $downloader = new KernelUpdateDownloader();

        $stagedFile = $downloader->getStagedFile();
        if ($stagedFile === null) {
            $this->jsonResponse(400, ['ok' => false, 'error' => 'No staged update to apply.']);
            return;
        }

        $applier = new KernelUpdateApplier();
        $result = $applier->apply($stagedFile);

        if (!$result->success) {
            $this->jsonResponse(500, ['ok' => false, 'error' => $result->error]);
            return;
        }

        $this->jsonResponse(200, [
            'ok' => true,
            'message' => "Kernel updated to v{$result->version}.",
            'version' => $result->version,
        ]);
    }

    // ------ DELETE /admin/updates/staging/delete ---

    public function deleteStaged(array $params = []): void
    {
        $file = trim($_GET['file'] ?? '');
        if ($file === '') {
            $this->jsonResponse(400, ['ok' => false, 'error' => 'No file specified.']);
            return;
        }

        $downloader = new KernelUpdateDownloader();
        $success = $downloader->deleteStaged($file);

        $this->jsonResponse($success ? 200 : 404, ['ok' => $success]);
    }

    // ------ POST /admin/updates/rollback ---

    public function rollback(array $params = []): void
    {
        $name = trim($_POST['backup_name'] ?? '');
        if ($name === '') {
            $this->flash('error', 'No backup specified.');
            header('Location: /admin/updates');
            exit;
        }

        $applier = new KernelUpdateApplier();
        $result = $applier->apply($applier->listBackups()[0]['dir'] ?? '');

        if (!$result->success) {
            $this->flash('error', 'Rollback failed: ' . $result->error);
        } else {
            $this->flash('success', 'Rolled back to v' . $result->version . '.');
        }

        header('Location: /admin/updates');
        exit;
    }

    // ------ Helpers ---

    /**
     * Send a JSON response and exit.
     */
    private function jsonResponse(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Build standard context variables for views.
     */
    private function ctx(): array
    {
        $rawConfig = $this->container->get('config');
        $principal = $this->container->get('principal');
        $viewsPath = __DIR__ . '/../../Views';
        $appName = $rawConfig['name'] ?? 'Kernel-Web';
        $displayName = ($principal['user']['display_name'] ?? '') !== ''
            ? $principal['user']['display_name']
            : $principal['user']['username'];
        $permissions = $principal['permissions'];
        return [$viewsPath, $appName, $displayName, $permissions];
    }

    /**
     * Write a flash message to the session.
     */
    private function flash(string $type, string $message): void
    {
        $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    }
}
