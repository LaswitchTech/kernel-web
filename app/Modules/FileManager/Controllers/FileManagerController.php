<?php

namespace App\Modules\FileManager\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\AuditLogRepository;
use App\Modules\FileManager\Services\FileManagerService;
use App\Modules\FileManager\Services\PathResolver;
use App\Modules\FileManager\Services\PreviewDetector;

/**
 * File Manager controller.
 *
 * Routes (all protected by ['WebAuth', 'WebPermission:files.manage']):
 *
 *   GET  /files                       → index()     Root list
 *   GET  /files/{rootId}              → browse()    Directory listing (?path=rel/path)
 *   POST /files/{rootId}/mkdir        → mkdir()     Create directory
 *   POST /files/{rootId}/upload       → upload()    Upload file
 *   GET  /files/{rootId}/download     → download()  Stream file download (?path=rel/path)
 *   GET  /files/{rootId}/preview      → preview()   Preview page for text, image, PDF
 *   GET  /files/{rootId}/serve        → serve()     Inline content stream (image/PDF only)
 *   POST /files/{rootId}/rename       → rename()    Rename file or directory
 *   POST /files/{rootId}/move         → move()      Move file or directory within root
 *   POST /files/{rootId}/delete       → delete()    Delete file or empty directory
 *
 * All paths are resolved through PathResolver before any filesystem operation.
 * Path traversal outside the configured root is structurally prevented.
 *
 * This controller is part of the reusable FileManager module.
 * It depends on AuditLogRepository for write-operation audit logging.
 * The service layer (FileManagerService + PathResolver) remains fully decoupled.
 */
class FileManagerController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /files — root list
    // -------------------------------------------------------------------------

    public function index(array $params = []): void
    {
        $principal   = $this->container->get('principal');
        $user        = $principal['user'];
        $permissions = $principal['permissions'];

        $appConfig   = $this->container->get('config');
                $appName     = $appConfig['name'] ?? 'Kernel-Web';
        $displayName = ($user['display_name'] ?? '') !== '' ? $user['display_name'] : $user['username'];

        $fmConfig = Config::load('filemanager');
        $roots    = array_filter(
            $fmConfig['roots'] ?? [],
            fn($r) => !empty($r['enabled'])
        );

        $pageTitle     = 'File Manager';
        $activeSection = 'File Manager';
        $viewsPath     = __DIR__ . '/../../../Views';

        ob_start();
        require $viewsPath . '/file-manager/index.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }

    // -------------------------------------------------------------------------
    // GET /files/{rootId} — directory listing
    // -------------------------------------------------------------------------

    public function browse(array $params = []): void
    {
        $root = $this->findRoot($params['rootId'] ?? '');

        if ($root === null) {
            http_response_code(404);
            echo '<h1>404 &mdash; Storage root not found</h1>';
            return;
        }

        $relativePath = $this->sanitizeRelativePath($_GET['path'] ?? '');
        $service      = $this->makeService();

        try {
            $entries = $service->listDirectory($root['path'], $relativePath);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo '<h1>404 &mdash; ' . htmlspecialchars($e->getMessage()) . '</h1>';
            return;
        }

        $principal   = $this->container->get('principal');
        $user        = $principal['user'];
        $permissions = $principal['permissions'];

        $appConfig   = $this->container->get('config');
                $appName     = $appConfig['name'] ?? 'Kernel-Web';
        $displayName = ($user['display_name'] ?? '') !== '' ? $user['display_name'] : $user['username'];

        $breadcrumbs  = $this->buildBreadcrumbs($root, $relativePath);
        $flash        = $_SESSION['fm_flash'] ?? null;
        unset($_SESSION['fm_flash']);
        $pageTitle    = 'File Manager';
        $activeSection = 'File Manager';
        $viewsPath    = __DIR__ . '/../../../Views';

        ob_start();
        require $viewsPath . '/file-manager/browse.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }

    // -------------------------------------------------------------------------
    // POST /files/{rootId}/mkdir — create directory
    // -------------------------------------------------------------------------

    public function mkdir(array $params = []): void
    {
        $root = $this->findRoot($params['rootId'] ?? '');

        if ($root === null) {
            http_response_code(404);
            echo '<h1>404 &mdash; Storage root not found</h1>';
            return;
        }

        $parent  = $this->sanitizeRelativePath($_POST['path'] ?? '');
        $name    = trim($_POST['name'] ?? '');
        $service = $this->makeService();
        $actorId = $this->actorId();

        try {
            $service->mkdir($root['path'], $parent, $name);
            $this->flash('success', "Folder \"{$name}\" created.");
            $this->auditLog($actorId, 'filemanager.mkdir', 'file_root', 0, [
                'root_id' => $root['id'],
                'path'    => $parent,
                'name'    => $name,
            ]);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirectToBrowse($root['id'], $parent);
    }

    // -------------------------------------------------------------------------
    // POST /files/{rootId}/upload — upload file
    // -------------------------------------------------------------------------

    public function upload(array $params = []): void
    {
        $root = $this->findRoot($params['rootId'] ?? '');

        if ($root === null) {
            http_response_code(404);
            echo '<h1>404 &mdash; Storage root not found</h1>';
            return;
        }

        $parent  = $this->sanitizeRelativePath($_POST['path'] ?? '');
        $actorId = $this->actorId();

        if (empty($_FILES['file'])) {
            $this->flash('error', 'No file received.');
            $this->redirectToBrowse($root['id'], $parent);
            return;
        }

        $service  = $this->makeService();
        $filename = basename($_FILES['file']['name'] ?? '');

        try {
            $service->upload($root['path'], $parent, $_FILES['file']);
            $this->flash('success', 'File uploaded successfully.');
            $this->auditLog($actorId, 'filemanager.upload', 'file_root', 0, [
                'root_id'  => $root['id'],
                'path'     => $parent,
                'filename' => $filename,
            ]);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirectToBrowse($root['id'], $parent);
    }

    // -------------------------------------------------------------------------
    // GET /files/{rootId}/download — stream file download
    // -------------------------------------------------------------------------

    public function download(array $params = []): void
    {
        $root = $this->findRoot($params['rootId'] ?? '');

        if ($root === null) {
            http_response_code(404);
            echo '<h1>404 &mdash; Storage root not found</h1>';
            return;
        }

        $relativePath = $this->sanitizeRelativePath($_GET['path'] ?? '');
        $service      = $this->makeService();

        try {
            $abs      = $service->absolutePath($root['path'], $relativePath);
            $filename = basename($abs);
            $size     = filesize($abs);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo '<h1>404 &mdash; ' . htmlspecialchars($e->getMessage()) . '</h1>';
            return;
        }

        // Detect MIME type for Content-Type.
        $mime = function_exists('mime_content_type') ? mime_content_type($abs) : 'application/octet-stream';
        if (!$mime) {
            $mime = 'application/octet-stream';
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . $size);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($abs);
        exit;
    }

    // -------------------------------------------------------------------------
    // GET /files/{rootId}/preview — preview page for text, image, and PDF files
    // -------------------------------------------------------------------------

    public function preview(array $params = []): void
    {
        $root = $this->findRoot($params['rootId'] ?? '');

        if ($root === null) {
            http_response_code(404);
            echo '<h1>404 &mdash; Storage root not found</h1>';
            return;
        }

        $relativePath = $this->sanitizeRelativePath($_GET['path'] ?? '');
        $service      = $this->makeService();

        try {
            $abs = $service->absolutePath($root['path'], $relativePath);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo '<h1>404 &mdash; ' . htmlspecialchars($e->getMessage()) . '</h1>';
            return;
        }

        $filename = basename($abs);
        $size     = (int) filesize($abs);
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($abs) ?: null) : null;
        $previewType = PreviewDetector::classify($filename, $mimeType);

        // For text: read content with size guard; escape for safe rendering.
        $textContent  = null;
        $textTooLarge = false;

        if ($previewType === 'text') {
            if ($size > PreviewDetector::TEXT_SIZE_LIMIT) {
                $textTooLarge = true;
            } else {
                $raw = file_get_contents($abs);
                $textContent = $raw !== false
                    ? htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    : null;
            }
        }

        $statData    = $service->stat($root['path'], $relativePath);
        $parentPath  = $this->parentOf($relativePath);
        $breadcrumbs = $this->buildBreadcrumbs($root, $relativePath);

        $principal   = $this->container->get('principal');
        $user        = $principal['user'];
        $permissions = $principal['permissions'];
        $appConfig   = $this->container->get('config');
                $appName     = $appConfig['name'] ?? 'Kernel-Web';
        $displayName = ($user['display_name'] ?? '') !== '' ? $user['display_name'] : $user['username'];

        $pageTitle     = 'Preview: ' . $filename;
        $activeSection = 'File Manager';
        $viewsPath     = __DIR__ . '/../../../Views';

        $serveUrl    = '/files/' . rawurlencode($root['id']) . '/serve?path='    . rawurlencode($relativePath);
        $downloadUrl = '/files/' . rawurlencode($root['id']) . '/download?path=' . rawurlencode($relativePath);
        $backUrl     = '/files/' . rawurlencode($root['id'])
                       . ($parentPath !== '' ? '?path=' . rawurlencode($parentPath) : '');

        ob_start();
        require $viewsPath . '/file-manager/preview.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/app.php';
    }

    // -------------------------------------------------------------------------
    // GET /files/{rootId}/serve — inline content stream (image and PDF only)
    // -------------------------------------------------------------------------

    public function serve(array $params = []): void
    {
        $root = $this->findRoot($params['rootId'] ?? '');

        if ($root === null) {
            http_response_code(404);
            return;
        }

        $relativePath = $this->sanitizeRelativePath($_GET['path'] ?? '');
        $service      = $this->makeService();

        try {
            $abs = $service->absolutePath($root['path'], $relativePath);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            return;
        }

        $filename = basename($abs);
        $mimeType = function_exists('mime_content_type')
            ? (mime_content_type($abs) ?: 'application/octet-stream')
            : 'application/octet-stream';

        $previewType = PreviewDetector::classify($filename, $mimeType);

        // Only image and PDF may be served inline.
        // Text is rendered in the preview page itself; other types are refused.
        if ($previewType !== 'image' && $previewType !== 'pdf') {
            http_response_code(403);
            return;
        }

        $size = (int) filesize($abs);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . $size);
        header('Cache-Control: private, no-cache');
        header('X-Content-Type-Options: nosniff');

        readfile($abs);
        exit;
    }

    // -------------------------------------------------------------------------
    // POST /files/{rootId}/rename — rename a file or directory
    // -------------------------------------------------------------------------

    public function rename(array $params = []): void
    {
        $root = $this->findRoot($params['rootId'] ?? '');

        if ($root === null) {
            http_response_code(404);
            echo '<h1>404 &mdash; Storage root not found</h1>';
            return;
        }

        $relativePath = $this->sanitizeRelativePath($_POST['path'] ?? '');
        $newName      = trim($_POST['name'] ?? '');
        $service      = $this->makeService();
        $actorId      = $this->actorId();

        // Parent path for redirect after rename.
        $parentPath = $this->parentOf($relativePath);

        try {
            $service->rename($root['path'], $relativePath, $newName);
            $this->flash('success', "Renamed to \"{$newName}\" successfully.");
            $this->auditLog($actorId, 'filemanager.rename', 'file_root', 0, [
                'root_id'  => $root['id'],
                'old_path' => $relativePath,
                'old_name' => basename($relativePath),
                'new_name' => $newName,
            ]);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirectToBrowse($root['id'], $parentPath);
    }

    // -------------------------------------------------------------------------
    // POST /files/{rootId}/move — move a file or directory
    // -------------------------------------------------------------------------

    public function move(array $params = []): void
    {
        $root = $this->findRoot($params['rootId'] ?? '');

        if ($root === null) {
            http_response_code(404);
            echo '<h1>404 &mdash; Storage root not found</h1>';
            return;
        }

        $relativePath    = $this->sanitizeRelativePath($_POST['path']        ?? '');
        $relativeDestDir = $this->sanitizeRelativePath($_POST['destination'] ?? '');
        $service         = $this->makeService();
        $actorId         = $this->actorId();

        // After move, redirect to the destination directory (on success)
        // or the item's current parent (on failure).
        $parentPath = $this->parentOf($relativePath);

        try {
            $service->move($root['path'], $relativePath, $relativeDestDir);
            $this->flash('success', 'Moved successfully.');
            $this->auditLog($actorId, 'filemanager.move', 'file_root', 0, [
                'root_id'     => $root['id'],
                'path'        => $relativePath,
                'name'        => basename($relativePath),
                'destination' => $relativeDestDir,
            ]);
            $this->redirectToBrowse($root['id'], $relativeDestDir);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirectToBrowse($root['id'], $parentPath);
        }
    }

    // -------------------------------------------------------------------------
    // POST /files/{rootId}/delete — delete file or empty directory
    // -------------------------------------------------------------------------

    public function delete(array $params = []): void
    {
        $root = $this->findRoot($params['rootId'] ?? '');

        if ($root === null) {
            http_response_code(404);
            echo '<h1>404 &mdash; Storage root not found</h1>';
            return;
        }

        $relativePath = $this->sanitizeRelativePath($_POST['path'] ?? '');
        $service      = $this->makeService();
        $actorId      = $this->actorId();

        // Determine parent path for post-delete redirect.
        $parentPath = $this->parentOf($relativePath);

        // Capture entry type before deletion — stat() will fail after the file is gone.
        $entryType = 'unknown';
        try {
            $entryType = $service->stat($root['path'], $relativePath)['type'];
        } catch (\RuntimeException $e) {
            // Stat failed (path may not exist). The delete attempt below will also fail,
            // surfacing its own error. Type logged as 'unknown' on the unlikely success path.
        }

        try {
            $service->delete($root['path'], $relativePath);
            $this->flash('success', 'Deleted successfully.');
            $this->auditLog($actorId, 'filemanager.delete', 'file_root', 0, [
                'root_id' => $root['id'],
                'path'    => $relativePath,
                'name'    => basename($relativePath),
                'type'    => $entryType,
            ]);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $parentPath = $relativePath; // Stay on current path so flash is visible.
        }

        $this->redirectToBrowse($root['id'], $parentPath);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Find a configured root by its ID.
     * Returns null if the root is not found or is disabled.
     */
    private function findRoot(string $rootId): ?array
    {
        if ($rootId === '') {
            return null;
        }

        $fmConfig = Config::load('filemanager');

        foreach ($fmConfig['roots'] ?? [] as $root) {
            if (($root['id'] ?? '') === $rootId && !empty($root['enabled'])) {
                return $root;
            }
        }

        return null;
    }

    /**
     * Sanitize a relative path from user input.
     * Strips leading slashes and trims whitespace.
     * The PathResolver performs the real safety check — this just normalizes input.
     */
    private function sanitizeRelativePath(string $path): string
    {
        return ltrim(trim($path), '/\\');
    }

    /**
     * Build the breadcrumb array for a given relative path.
     *
     * Returns an array of ['label' => string, 'url' => string|null] entries.
     * The last entry has url=null (current location, not clickable).
     */
    private function buildBreadcrumbs(array $root, string $relativePath): array
    {
        $crumbs = [
            ['label' => $root['label'], 'url' => '/files/' . rawurlencode($root['id'])],
        ];

        if ($relativePath === '') {
            // At root — make the root entry non-clickable.
            $crumbs[0]['url'] = null;
            return $crumbs;
        }

        $parts = explode('/', $relativePath);
        $built = '';

        foreach ($parts as $i => $part) {
            $built   = $built === '' ? $part : $built . '/' . $part;
            $isLast  = ($i === count($parts) - 1);
            $crumbs[] = [
                'label' => $part,
                'url'   => $isLast ? null : ('/files/' . rawurlencode($root['id']) . '?path=' . rawurlencode($built)),
            ];
        }

        return $crumbs;
    }

    /**
     * Instantiate the FileManagerService with a fresh PathResolver.
     */
    private function makeService(): FileManagerService
    {
        return new FileManagerService(new PathResolver());
    }

    /**
     * Return the parent relative path of a given relative path.
     * Returns '' when the path is already at root level.
     */
    private function parentOf(string $relativePath): string
    {
        $parent = ltrim(dirname($relativePath), '/.');
        return ($parent === '' || $parent === '.') ? '' : $parent;
    }

    /**
     * Redirect back to the browse view for a given root and path.
     */
    private function redirectToBrowse(string $rootId, string $relativePath): void
    {
        $url = '/files/' . rawurlencode($rootId);

        if ($relativePath !== '') {
            $url .= '?path=' . rawurlencode($relativePath);
        }

        header('Location: ' . $url);
        exit;
    }

    /**
     * Write a flash message to the session for the next request.
     */
    private function flash(string $type, string $message): void
    {
        $_SESSION['fm_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Return the ID of the currently authenticated user, or 0 if unavailable.
     */
    private function actorId(): int
    {
        return (int) ($this->container->get('principal')['user']['id'] ?? 0);
    }

    /**
     * Append one audit log entry.
     *
     * Always wrapped in try/catch — a logging failure must never abort the main operation.
     *
     * Convention for File Manager entries:
     *   entity_type = 'file_root'
     *   entity_id   = 0  (no DB-backed file record)
     *   action      = 'filemanager.<verb>'  (mkdir, upload, rename, move, delete)
     */
    private function auditLog(int $actorId, string $action, string $entityType, int $entityId, array $meta = []): void
    {
        try {
            (new AuditLogRepository($this->container->get('db')))
                ->log($actorId ?: null, $action, $entityType, $entityId, $meta);
        } catch (\Throwable $e) {
            // Intentionally swallowed — audit logging must never abort the main operation.
        }
    }
}
