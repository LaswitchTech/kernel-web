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

        $breadcrumbs = [
            ['label' => 'Extensions', 'url' => null],
        ];

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

        $breadcrumbs = [
            ['label' => 'Extensions', 'url' => '/admin/extensions'],
            ['label' => 'Catalog', 'url' => null],
        ];

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

        $breadcrumbs = [
            ['label' => 'Extensions', 'url' => '/admin/extensions'],
            ['label' => 'Catalog', 'url' => '/admin/extensions/catalog'],
            ['label' => 'Submit', 'url' => null],
        ];

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

        $breadcrumbs = [
            ['label' => 'Extensions', 'url' => '/admin/extensions'],
            ['label' => 'Catalog', 'url' => '/admin/extensions/catalog'],
            ['label' => 'Review', 'url' => null],
        ];

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
     * Staged install for an approved catalog entry.
     *
     * Copies files from /storage/extension-staging/{slug} to lib/{type_plural}/{slug}.
     * Uses realpath() to prevent path traversal.
     * Marks the catalog entry as installed only after copy succeeds.
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

        $slug  = $extension['slug'];
        $type  = $extension['type'];

        // Validate type
        $validTypes = ['plugin', 'theme', 'layout'];
        if (!in_array($type, $validTypes, true)) {
            $this->flash('error', 'Invalid extension type: ' . htmlspecialchars($type));
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Validate slug format
        if (!preg_match('/^[a-z][a-z0-9_-]+$/', $slug)) {
            $this->flash('error', 'Invalid extension slug: ' . htmlspecialchars($slug));
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Resolve staging base directory
        $stagingBase = realpath(__DIR__ . '/../../../../storage/extension-staging');
        if ($stagingBase === false) {
            $this->flash('error', 'Staging directory does not exist. Create <code>/storage/extension-staging/</code> and place extension files there.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Resolve source directory
        $sourceDir = $stagingBase . '/' . $slug;
        $resolvedSource = realpath($sourceDir);
        if ($resolvedSource === false || !is_dir($resolvedSource)) {
            $this->flash('error', 'Staging directory not found at <code>/storage/extension-staging/' . htmlspecialchars($slug) . '/</code>. Place extension files there first.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Path traversal check: source must be under staging
        if (strpos($resolvedSource, $stagingBase . DIRECTORY_SEPARATOR) !== 0 && $resolvedSource !== $stagingBase) {
            $this->flash('error', 'Staging path escapes the trusted staging directory. Installation refused.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Resolve lib base directory
        $libBase = realpath(__DIR__ . '/../../../lib');
        if ($libBase === false) {
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
        $subDir = $typeDirs[$type];

        $targetDir = $libBase . '/' . $subDir . '/' . $slug;

        // Target must not already exist
        if (is_dir($targetDir)) {
            $this->flash('error', 'Target directory already exists — would overwrite: <code>' . htmlspecialchars($targetDir) . '</code>');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Perform the copy
        if (!$this->copyDir($resolvedSource, $targetDir)) {
            $this->flash('error', 'Failed to copy extension files from staging to target directory.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Run install lifecycle hook and migrations.
        $pluginsDir = realpath(__DIR__ . '/../../../lib/plugins');
        if ($pluginsDir !== false && is_dir($pluginsDir)) {
            $loader = new \App\Core\Plugins\PluginLoader($pluginsDir, $this->container);

            // Run the plugin's install lifecycle hook.
            $installOk = $loader->runLifecycleHook($extension['name'], 'install');

            // Run migrations declared by the plugin (read from plugin.json).
            $pluginJsonPath = $targetDir . '/plugin.json';
            if (is_file($pluginJsonPath)) {
                $pluginData = json_decode(file_get_contents($pluginJsonPath), true);
                $migrations = $pluginData['migrations'] ?? [];
                if (!empty($migrations)) {
                    $migrationRunner = new \App\Core\MigrationRunner(
                        $this->container->get('db'),
                        $targetDir . '/migrations'
                    );
                    foreach ($migrationRunner->pending() as $migrationFile) {
                        $migrationName = basename($migrationFile, '.php');
                        $migrationClass = $this->migrationClassFromName($migrationName);
                        require_once $migrationFile;

                        if (!class_exists($migrationClass)) {
                            $this->flash('error', "Migration class '{$migrationClass}' not found in " . basename($migrationFile));
                            header('Location: /admin/extensions/catalog');
                            exit;
                        }

                        $migration = new $migrationClass($this->container->get('db'));
                        if (!($migration instanceof \App\Core\Migration)) {
                            $this->flash('error', "Migration '{$migrationClass}' must extend App\\Core\\Migration");
                            header('Location: /admin/extensions/catalog');
                            exit;
                        }

                        $migration->up();
                        $migrationRunner->record($migrationName);
                    }
                }
            }

            if (!$installOk) {
                $this->flash('error', 'Extension "' . $extension['name'] . '" installed but the install hook failed. Check the logs for details.');
            }
        }

        // Mark as installed in the catalog
        $catalog->markInstalled($id);

        $this->flash('success', 'Extension "' . $extension['name'] . '" (v' . $extension['version'] . ') installed to <code>' . htmlspecialchars($targetDir) . '</code>.');
        header('Location: /admin/extensions/catalog');
        exit;
    }

    /**
     * Recursively copy a directory tree.
     *
     * Skips symbolic links to prevent traversal attacks.
     * Returns true on success, false on any failure.
     *
     * @param string $source
     * @param string $target
     * @return bool
     */
    private function copyDir(string $source, string $target): bool
    {
        if (!@mkdir($target, 0755, true)) {
            return false;
        }

        $dir = opendir($source);
        if (!$dir) {
            @rmdir($target);
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $source . '/' . $file;
            $tgtPath = $target . '/' . $file;

            if (is_link($srcPath)) {
                // Skip symbolic links to prevent traversal attacks
                continue;
            }

            if (is_dir($srcPath)) {
                if (!$this->copyDir($srcPath, $tgtPath)) {
                    closedir($dir);
                    $this->removeDir($target);
                    return false;
                }
            } else {
                if (!@copy($srcPath, $tgtPath)) {
                    closedir($dir);
                    $this->removeDir($target);
                    return false;
                }
                @chmod($tgtPath, 0644);
            }
        }

        closedir($dir);
        return true;
    }

    /**
     * Recursively remove a directory tree (for cleanup on failure).
     *
     * @param string $dir
     * @return void
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * Enable an installed catalog extension.
     *
     * Validates the extension is installed and its directory exists.
     * Sets is_enabled = 1 in the catalog database.
     */
    public function handleEnable(array $params = []): void
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

        if ((int) $extension['is_installed'] !== 1) {
            $this->flash('error', 'Extension "' . $extension['name'] . '" must be installed before enabling.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        if ((int) $extension['is_enabled'] === 1) {
            $this->flash('error', 'Extension "' . $extension['name'] . '" is already enabled.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        $slug  = $extension['slug'];
        $type  = $extension['type'];

        if (!in_array($type, ['plugin', 'theme', 'layout'], true)) {
            $this->flash('error', 'Invalid extension type: ' . htmlspecialchars($type));
            header('Location: /admin/extensions/catalog');
            exit;
        }

        if (!preg_match('/^[a-z][a-z0-9_-]+$/', $slug)) {
            $this->flash('error', 'Invalid extension slug: ' . htmlspecialchars($slug));
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Validate extension directory exists
        $libBase = realpath(__DIR__ . '/../../../lib');
        if ($libBase === false) {
            $this->flash('error', 'Cannot resolve base lib directory.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        $typeDirs = [
            'plugin' => 'plugins',
            'theme'  => 'themes',
            'layout' => 'layouts',
        ];
        $targetDir = $libBase . '/' . $typeDirs[$type] . '/' . $slug;

        if (!is_dir($targetDir)) {
            $this->flash('error', 'Extension directory not found: <code>' . htmlspecialchars($targetDir) . '</code>. Cannot enable without installed files.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Run enable lifecycle hook.
        $this->triggerLifecycle($extension['slug'], 'enable');

        $catalog->markEnabled($id);

        $this->flash('success', 'Extension "' . $extension['name'] . '" enabled.');
        header('Location: /admin/extensions/catalog');
        exit;
    }

    /**
     * Disable an installed catalog extension.
     *
     * Sets is_enabled = 0 in the catalog database.
     */
    public function handleDisable(array $params = []): void
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

        if ((int) $extension['is_installed'] !== 1) {
            $this->flash('error', 'Extension "' . $extension['name'] . '" must be installed before disabling.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        if ((int) $extension['is_enabled'] === 0) {
            $this->flash('error', 'Extension "' . $extension['name'] . '" is already disabled.');
            header('Location: /admin/extensions/catalog');
            exit;
        }

        // Run disable lifecycle hook.
        $this->triggerLifecycle($extension['slug'], 'disable');

        $catalog->markDisabled($id);

        $this->flash('success', 'Extension "' . $extension['name'] . '" disabled.');
        header('Location: /admin/extensions/catalog');
        exit;
    }

    /**
     * Trigger the lifecycle hook for a plugin identified by slug.
     */
    private function triggerLifecycle(string $slug, string $event): void
    {
        $pluginsDir = realpath(__DIR__ . '/../../../lib/plugins');
        if ($pluginsDir === false || !is_dir($pluginsDir)) {
            return;
        }

        $loader = new \App\Core\Plugins\PluginLoader($pluginsDir, $this->container);
        $loader->runLifecycleHook($slug, $event);
    }

    /**
     * Derive a migration class name from its filename.
     * 0001_create_users -> CreateUsersTable
     */
    private function migrationClassFromName(string $name): string
    {
        $stripped = preg_replace('/^\d+_/', '', $name);
        return str_replace('_', '', ucwords($stripped, '_'));
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
