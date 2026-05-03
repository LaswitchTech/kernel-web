<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\Extensions\ExtensionDiscoveryService;

/**
 * Admin Extensions — read-only listing of discovered extensions.
 *
 * Routes:
 *   GET /admin/extensions     → index()  Extension listing
 *
 * Protected by WebPermission:extensions.manage.
 */
class ExtensionsController extends Controller
{
    public function index(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        $basePath = realpath(__DIR__ . '/../../../lib');

        $pageTitle     = 'Extensions';
        $activeSection = 'Admin Extensions';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $user['display_name'] ?? $user['username'];
        $permissions   = $perms;

        $extensions = [];
        if ($basePath !== false) {
            $extensions = (new ExtensionDiscoveryService($basePath))->discover();
        }

        ob_start();
        require $viewsPath . '/admin/extensions/index.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    /**
     * Browse catalog-managed extensions.
     */
    public function catalog(array $params = []): void
    {
        $principal  = $this->container->get('principal');
        $user       = $principal['user'];
        $perms      = $principal['permissions'];

        $config     = $this->container->get('config');
        $viewsPath  = __DIR__ . '/../../Views';

        $catalog    = new \App\Services\Extensions\CatalogService(
            new \App\Models\CatalogExtensionRepository(
                $this->container->get('db')
            )
        );

        $extensions = $catalog->listAll();

        $pageTitle  = 'Extension Catalog';
        $activeSection = 'Admin Extensions';
        $appName    = $config['name'] ?? 'Kernel-Web';
        $displayName = $user['display_name'] ?? $user['username'];
        $permissions = $perms;

        ob_start();
        require $viewsPath . '/admin/extensions/catalog.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    /**
     * Show local catalog submission form.
     */
    public function submitForm(array $params = []): void
    {
        $principal  = $this->container->get('principal');
        $user       = $principal['user'];
        $perms      = $principal['permissions'];

        $config     = $this->container->get('config');
        $viewsPath  = __DIR__ . '/../../Views';

        $pageTitle    = 'Submit Extension';
        $activeSection = 'Admin Extensions';
        $appName      = $config['name'] ?? 'Kernel-Web';
        $displayName  = $user['display_name'] ?? $user['username'];
        $permissions  = $perms;
        $errors       = [];
        $old          = [];
        $flash        = $this->popFlash();

        ob_start();
        require $viewsPath . '/admin/extensions/submit.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    /**
     * Handle local catalog submission.
     */
    public function handleSubmit(array $params = []): void
    {
        $principal  = $this->container->get('principal');
        $user       = $principal['user'];
        $perms      = $principal['permissions'];

        $config     = $this->container->get('config');
        $viewsPath  = __DIR__ . '/../../Views';

        $catalog    = new \App\Services\Extensions\CatalogService(
            new \App\Models\CatalogExtensionRepository(
                $this->container->get('db')
            )
        );

        $old = [
            'name'         => trim($_POST['name']         ?? ''),
            'slug'         => trim($_POST['slug']         ?? ''),
            'type'         => trim($_POST['type']         ?? 'plugin'),
            'version'      => trim($_POST['version']      ?? ''),
            'description'  => trim($_POST['description']  ?? ''),
            'author'       => trim($_POST['author']       ?? ''),
            'download_url' => trim($_POST['download_url'] ?? ''),
            'repo_url'     => trim($_POST['repo_url']     ?? ''),
            'requirements' => trim($_POST['requirements'] ?? ''),
            'dependencies' => trim($_POST['dependencies'] ?? ''),
            'checksum'     => trim($_POST['checksum']     ?? ''),
        ];

        $result = $catalog->create([
            'name'         => $old['name'],
            'slug'         => $old['slug'],
            'type'         => $old['type'],
            'version'      => $old['version'],
            'description'  => $old['description'],
            'author'       => $old['author'],
            'download_url' => $old['download_url'],
            'repo_url'     => $old['repo_url'] === '' ? null : $old['repo_url'],
            'requirements' => $old['requirements'] === '' ? '[]' : $old['requirements'],
            'dependencies' => $old['dependencies'] === '' ? '[]' : $old['dependencies'],
        ]);

        if (!$result['success']) {
            $errors   = $result['errors'];
            $pageTitle  = 'Submit Extension';
            $activeSection = 'Admin Extensions';
            $appName    = $config['name'] ?? 'Kernel-Web';
            $displayName = $user['display_name'] ?? $user['username'];
            $permissions = $perms;
            $flash      = null;

            ob_start();
            require $viewsPath . '/admin/extensions/submit.php';
            $content = ob_get_clean();

            http_response_code(422);
            require $viewsPath . '/layouts/panel.php';
            return;
        }

        $this->flash('success', 'Extension "' . $old['name'] . '" submitted for review.');
        header('Location: /admin/extensions/catalog');
        exit;
    }

    /**
     * Review pending catalog submissions.
     */
    public function review(array $params = []): void
    {
        $principal  = $this->container->get('principal');
        $user       = $principal['user'];
        $perms      = $principal['permissions'];

        $config     = $this->container->get('config');
        $viewsPath  = __DIR__ . '/../../Views';

        $catalog    = new \App\Services\Extensions\CatalogService(
            new \App\Models\CatalogExtensionRepository(
                $this->container->get('db')
            )
        );

        $pending    = $catalog->listPending();

        $pageTitle    = 'Review Submissions';
        $activeSection = 'Admin Extensions';
        $appName      = $config['name'] ?? 'Kernel-Web';
        $displayName  = $user['display_name'] ?? $user['username'];
        $permissions  = $perms;
        $flash        = $this->popFlash();

        ob_start();
        require $viewsPath . '/admin/extensions/review.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    /**
     * Approve a pending catalog entry.
     */
    public function handleApprove(array $params = []): void
    {
        $principal  = $this->container->get('principal');
        $user       = $principal['user'];
        $perms      = $principal['permissions'];

        $config     = $this->container->get('config');
        $viewsPath  = __DIR__ . '/../../Views';

        $catalog    = new \App\Services\Extensions\CatalogService(
            new \App\Models\CatalogExtensionRepository(
                $this->container->get('db')
            )
        );

        $id         = (int) ($params['id'] ?? 0);
        $extension  = $catalog->getById($id);

        if ($extension === null) {
            $this->flash('error', 'Extension not found.');
            header('Location: /admin/extensions/catalog/review');
            exit;
        }

        $result = $catalog->approve($id);

        if (!$result['success']) {
            $this->flash('error', implode('; ', $result['errors']));
        } else {
            $this->flash('success', 'Extension "' . $extension['name'] . '" approved.');
        }

        header('Location: /admin/extensions/catalog/review');
        exit;
    }

    /**
     * Reject a pending catalog entry.
     */
    public function handleReject(array $params = []): void
    {
        $principal  = $this->container->get('principal');
        $user       = $principal['user'];
        $perms      = $principal['permissions'];

        $config     = $this->container->get('config');
        $viewsPath  = __DIR__ . '/../../Views';

        $catalog    = new \App\Services\Extensions\CatalogService(
            new \App\Models\CatalogExtensionRepository(
                $this->container->get('db')
            )
        );

        $id         = (int) ($params['id'] ?? 0);
        $extension  = $catalog->getById($id);

        if ($extension === null) {
            $this->flash('error', 'Extension not found.');
            header('Location: /admin/extensions/catalog/review');
            exit;
        }

        $result = $catalog->reject($id);

        if (!$result['success']) {
            $this->flash('error', implode('; ', $result['errors']));
        } else {
            $this->flash('success', 'Extension "' . $extension['name'] . '" rejected.');
        }

        header('Location: /admin/extensions/catalog/review');
        exit;
    }

    /**
     * Dry-run install for an approved catalog entry.
     *
     * Validates type, slug, path safety, and non-overwrite.
     * Does not copy files — reports where the extension would be installed.
     */
    public function handleInstall(array $params = []): void
    {
        $principal  = $this->container->get('principal');
        $user       = $principal['user'];
        $perms      = $principal['permissions'];

        $config     = $this->container->get('config');
        $viewsPath  = __DIR__ . '/../../Views';

        $catalog    = new \App\Services\Extensions\CatalogService(
            new \App\Models\CatalogExtensionRepository(
                $this->container->get('db')
            )
        );

        $id         = (int) ($params['id'] ?? 0);
        $extension  = $catalog->getById($id);

        if ($extension === null) {
            $this->flash('error', 'Extension not found.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        if ($extension['status'] !== 'approved') {
            $this->flash('error', 'Only approved extensions can be installed.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        if ((int) $extension['is_installed'] === 1) {
            $this->flash('error', 'Extension "' . $extension['name'] . '" is already installed.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        $slug       = $extension['slug'];
        $type       = $extension['type'];
        $version    = $extension['version'];
        $author     = $extension['author'] ?? '';
        $description = $extension['description'] ?? '';
        $downloadUrl = $extension['download_url'] ?? '';

        // Validate type and resolve target path
        $validTypes = ['plugin', 'theme', 'layout'];
        $invalidMsg = '';

        if (!in_array($type, $validTypes, true)) {
            $invalidMsg = 'Invalid extension type: ' . htmlspecialchars($type);
        } elseif (!preg_match('/^[a-z][a-z0-9_-]+$/', $slug)) {
            $invalidMsg = 'Invalid extension slug: ' . htmlspecialchars($slug);
        }

        if ($invalidMsg !== '') {
            $this->flash('error', $invalidMsg);
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Resolve and validate target directory
        $basePath = realpath(__DIR__ . '/../../../lib');
        if ($basePath === false) {
            $this->flash('error', 'Cannot resolve base lib directory.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Type-specific subdirectory mapping
        $typeDirs = [
            'plugin' => 'plugins',
            'theme'  => 'themes',
            'layout' => 'layouts',
        ];

        $subDir    = $typeDirs[$type];
        $targetDir = $basePath . '/' . $subDir . '/' . $slug;

        // Path traversal check: target must be under lib
        if (strpos($targetDir, realpath($basePath) . DIRECTORY_SEPARATOR) !== 0) {
            $this->flash('error', 'Install path escapes the lib directory. Installation refused.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Non-overwrite check
        $overwritesExisting = false;
        if (is_dir($targetDir)) {
            $overwritesExisting = true;
        }

        // No remote download. Show dry-run result.
        $installMessage = 'Install dry-run for "' . $extension['name'] . '" (v' . $version . '): ';
        $installMessage .= 'would install to <code>' . htmlspecialchars($targetDir) . '</code>. ';
        $installMessage .= 'No files written. Remote download and ZIP extraction are deferred.';

        if ($overwritesExisting) {
            $installMessage .= '<div class="alert alert-warning mt-2">Target directory already exists — would overwrite.</div>';
        }

        $this->flash('success', $installMessage);
        header('Location: /admin/extensions/catalog');
        exit;
    }

    // ------ Flash Helpers ------

    /**
     * Write a flash message to the session for the next request.
     */
    private function flash(string $type, string $message): void
    {
        $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Read and clear the flash message from the session.
     */
    private function popFlash(): ?array
    {
        $flash = $_SESSION['admin_flash'] ?? null;
        unset($_SESSION['admin_flash']);
        return $flash;
    }
}
