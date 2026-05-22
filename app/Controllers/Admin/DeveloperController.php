<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\SettingsRegistry;
use App\Services\SystemSettingService;
use App\Models\SystemSettingRepository;

/**
 * Developer Tools admin controller — reads-only tools page.
 *
 * Only accessible when debug mode is enabled (APP_DEBUG=true).
 * When disabled, returns 404 to avoid exposing the page existence.
 */
class DeveloperController extends Controller
{
    public function tools(array $params = []): void
    {
        $this->registerDeveloperSection();

        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        // Derive $appConfig the same way ViewGlobals does (flat or nested config).
        $appConfig = is_array($config['app'] ?? null) ? $config['app'] : $config;

        // Override app config with DB-stored developer settings (if any).
        // DB is the highest-priority layer for runtime-toggled settings.
        $svc = new SystemSettingService(
            new SystemSettingRepository($this->container->get('db'))
        );
        if ($svc->get('developer.developer') !== null) {
            $appConfig['developer'] = $svc->getBool('developer.developer', $appConfig['developer'] ?? false);
        }
        if ($svc->get('developer.debug') !== null) {
            $appConfig['debug'] = $svc->getBool('developer.debug', $appConfig['debug'] ?? false);
        }
        if ($svc->get('developer.dev_console') !== null) {
            $appConfig['dev_console'] = $svc->getBool('developer.dev_console', $appConfig['dev_console'] ?? false);
        }

        // Check if debug mode is enabled.
        // config['app']['debug'] mirrors config/app.php 'debug' key.
        $debugConfig = $config['app']['debug'] ?? $config['debug'] ?? false;
        $debug       = (bool) filter_var($debugConfig, FILTER_VALIDATE_BOOLEAN);

        if (!$debug) {
            // Return 404 — don't expose that this route exists in production.
            http_response_code(404);
            $this->handle404();
            return;
        }

        $pageTitle     = 'Developer Tools';
        $activeSection = 'Developer Tools';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $user['display_name'] ?? $user['username'];
        $permissions   = $perms;
        $flash         = $this->popFlash();

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Developer Tools', 'url' => null],
        ];

        // List of planned developer tools.
        $plannedTools = [
            [
                'name'       => 'Create Plugin Scaffold',
                'icon'       => 'bi bi-plug',
                'status'     => 'Planned',
                'description' => 'Generate a new plugin directory structure with manifest, routes.php, and skeleton controllers.',
            ],
            [
                'name'       => 'Create Theme Scaffold',
                'icon'       => 'bi bi-palette',
                'status'     => 'Planned',
                'description' => 'Generate a new theme directory with theme.json, less/app.less, and Bootstrap token overrides.',
            ],
            [
                'name'       => 'Create Layout Scaffold',
                'icon'       => 'bi bi-layout-sidebar',
                'status'     => 'Planned',
                'description' => 'Generate a new layout file with standard panel regions and hook points.',
            ],
            [
                'name'       => 'Copy Example Code',
                'icon'       => 'bi bi-file-earmark-code',
                'status'     => 'Planned',
                'description' => 'Copy example extension code (lifecycle hooks, menu registrations, route patterns) to a new extension.',
            ],
            [
                'name'       => 'Configure Local Repository',
                'icon'       => 'bi bi-gear',
                'status'     => 'Planned',
                'description' => 'Configure a local repository for extension development and testing.',
            ],
            [
                'name'       => 'Validate Manifests',
                'icon'       => 'bi bi-clipboard-check',
                'status'     => 'Planned',
                'description' => 'Validate extension manifests (plugin.json, theme.json, layout.json) for common errors.',
            ],
            [
                'name'       => 'Run Development Diagnostics',
                'icon'       => 'bi bi-clipboard-data',
                'status'     => 'Planned',
                'description' => 'Run diagnostics: check plugin loading, theme discovery, layout resolution, and config state.',
            ],
        ];

        ob_start();
        require $viewsPath . '/admin/developer/tools.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    /**
     * Return a 404 response using existing error page behavior.
     */
    private function handle404(): void
    {
        \App\Core\ErrorPage::render(404, 'The developer tools page is not available in production mode.');
    }

    /**
     * Register the developer settings section if not already registered.
     */
    private function registerDeveloperSection(): void
    {
        if (SettingsRegistry::getSection('developer') !== null) {
            return;
        }

        SettingsRegistry::addSection([
            'id'       => 'developer',
            'label'    => 'Developer Settings',
            'column'   => 'left',
            'order'    => 5,
            'permission' => null,
            'keys'     => ['developer.developer', 'developer.debug', 'developer.dev_console'],
            'render'   => function (array $context): string {
                $settings = $context['settings'] ?? [];
                $errors   = $context['errors'] ?? [];

                $developerChecked   = ($settings['developer.developer'] ?? false) ? 'checked' : '';
                $debugChecked       = ($settings['developer.debug'] ?? false) ? 'checked' : '';
                $devConsoleChecked  = ($settings['developer.dev_console'] ?? false) ? 'checked' : '';

                $ec = function(string $key) use ($errors): string {
                    return isset($errors[$key]) ? 'is-invalid' : '';
                };

                return '<div class="mb-3">'
                    . '<label class="form-label small fw-semibold">Developer Mode</label>'
                    . '<div class="form-check form-switch mb-1">'
                    . '<input class="form-check-input ' . $ec('developer.developer') . '" type="checkbox" role="switch" name="developer_developer" id="developer_developer" ' . $developerChecked . '>'
                    . '<label class="form-check-label small" for="developer_developer">Enable developer features across the application</label>'
                    . '</div>'
                    . '<div class="form-text">Controls visibility of Developer section, scaffold generator, and debug-gated features.</div>'
                    . '<div class="invalid-feedback">' . htmlspecialchars($errors['developer.developer'] ?? '') . '</div>'
                    . '</div>'
                    . '<div class="mb-3">'
                    . '<label class="form-label small fw-semibold">Debug Mode</label>'
                    . '<div class="form-check form-switch mb-1">'
                    . '<input class="form-check-input ' . $ec('developer.debug') . '" type="checkbox" role="switch" name="developer_debug" id="developer_debug" ' . $debugChecked . '>'
                    . '<label class="form-check-label small" for="developer_debug">Enable debug mode (error details, stack traces)</label>'
                    . '</div>'
                    . '<div class="invalid-feedback">' . htmlspecialchars($errors['developer.debug'] ?? '') . '</div>'
                    . '</div>'
                    . '<div class="mb-0">'
                    . '<label class="form-label small fw-semibold">Dev Console</label>'
                    . '<div class="form-check form-switch mb-1">'
                    . '<input class="form-check-input ' . $ec('developer.dev_console') . '" type="checkbox" role="switch" name="developer_dev_console" id="developer_dev_console" ' . $devConsoleChecked . '>'
                    . '<label class="form-check-label small" for="developer_dev_console">Enable floating developer console (offcanvas)</label>'
                    . '</div>'
                    . '<div class="form-text">Controls whether the floating dev console button and panel render on the site.</div>'
                    . '<div class="invalid-feedback">' . htmlspecialchars($errors['developer.dev_console'] ?? '') . '</div>'
                    . '</div>';
            },
            'validate' => function (array $input): array {
                $errors = [];
                foreach (['developer.developer', 'developer.debug', 'developer.dev_console'] as $key) {
                    if (!array_key_exists($key, $input)) {
                        $errors[$key] = 'This field is required.';
                    }
                }
                return $errors;
            },
            'save'   => function (array $input, SystemSettingService $svc): void {
                foreach (['developer.developer', 'developer.debug', 'developer.dev_console'] as $key) {
                    if (isset($input[$key])) {
                        $svc->set($key, $input[$key]);
                    }
                }
            },
            'source' => 'core',
        ]);
    }

    // ------
    // AJAX POST /admin/developer/settings — Save debug/dev_console toggles
    // ------

    public function ajaxSaveSettings(array $params = []): void
    {
        $this->registerDeveloperSection();

        $principal = $this->container->get('principal');
        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        // Check debug mode.
        $debugConfig = $config['app']['debug'] ?? $config['debug'] ?? false;
        $debug       = (bool) filter_var($debugConfig, FILTER_VALIDATE_BOOLEAN);

        if (!$debug) {
            http_response_code(404);
            $this->handle404();
            return;
        }

        $svc = new SystemSettingService(
            new SystemSettingRepository($this->container->get('db'))
        );

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
            return;
        }

        // Map POST field names to DB keys.
        $map = [
            'developer_developer'   => 'developer.developer',
            'developer_debug'       => 'developer.debug',
            'developer_dev_console' => 'developer.dev_console',
        ];

        $savedKeys = [];
        foreach ($map as $postKey => $dbKey) {
            if (isset($_POST[$postKey])) {
                $val = '1';
            } elseif (array_key_exists($postKey, $_POST)) {
                $val = '0';
            } else {
                continue; // Not submitted — skip this key.
            }
            $svc->set($dbKey, $val);
            $savedKeys[] = $dbKey;
        }

        // Audit log.
        try {
            $actorId = (int) ($principal['user']['id'] ?? 0);
            (new \App\Models\AuditLogRepository($this->container->get('db')))
                ->log($actorId, 'developer.settings_update', 'developer_settings', 0, [
                    'keys_saved' => $savedKeys,
                ]);
        } catch (\Throwable $e) {
            // Intentionally swallowed.
        }

        $message = 'Developer settings saved: ' . implode(', ', $savedKeys);
        if (empty($savedKeys)) {
            $message = 'No settings changed.';
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'message' => $message]);
        exit;
    }

    // ------
    // GET/POST /admin/developer/scaffold
    // ------

    public function createScaffold(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

        // Check debug mode.
        $debugConfig = $config['app']['debug'] ?? $config['debug'] ?? false;
        $debug       = (bool) filter_var($debugConfig, FILTER_VALIDATE_BOOLEAN);

        if (!$debug) {
            http_response_code(404);
            $this->handle404();
            return;
        }

        $pageTitle     = 'Scaffold Generator';
        $activeSection = 'Scaffold Generator';
        $appName       = $config['name'] ?? 'Kernel-Web';
        $displayName   = $user['display_name'] ?? $user['username'];
        $permissions   = $perms;
        $flash         = $this->popFlash();
        $fileList      = null;
        $scaffoldSlug  = null;

        // Handle POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->handleScaffoldGeneration();

            if ($result['ok']) {
                $fileList   = $result['files'];
                $scaffoldSlug = $result['slug'];
                $this->setFlash('success', 'Scaffold generated at /storage/extension-staging/' . $result['slug'] . '/');
                ob_start();
                require $viewsPath . '/admin/developer/scaffold.php';
                require $viewsPath . '/layouts/panel.php';
                return;
            } else {
                $flashMessages = [
                    ['type' => 'danger', 'message' => $result['error']],
                ];
                // Pass error to the form via session flash (already stored above).
            }
        }

        ob_start();
        require $viewsPath . '/admin/developer/scaffold.php';
        require $viewsPath . '/layouts/panel.php';
    }

    /**
     * Handle scaffold generation: validate, load templates, write to staging.
     */
    private function handleScaffoldGeneration(): array
    {
        $type     = trim((string) $this->input('type', ''));
        $name     = trim((string) $this->input('name', ''));
        $slug     = trim((string) $this->input('slug', ''));
        $version  = trim((string) $this->input('version', ''));
        $desc     = trim((string) $this->input('description', ''));
        $author   = trim((string) $this->input('author', ''));
        $namespace = trim((string) $this->input('namespace', ''));

        // Validation
        if ($type === '' || !in_array($type, ['plugin', 'theme', 'layout'], true)) {
            return ['ok' => false, 'error' => 'Type is required and must be plugin, theme, or layout.'];
        }

        if ($name === '' || strlen($name) > 100) {
            return ['ok' => false, 'error' => 'Name is required (max 100 characters).'];
        }

        if ($slug === '' || strlen($slug) > 63 || !preg_match('/^[a-z][a-z0-9-]*$/', $slug)) {
            return ['ok' => false, 'error' => 'Slug is required, must start with a lowercase letter, and contain only lowercase letters, numbers, and hyphens.'];
        }

        if ($version === '' || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return ['ok' => false, 'error' => 'Version must be a semantic version (e.g. 0.1.0).'];
        }

        if ($desc === '' || strlen($desc) > 500) {
            return ['ok' => false, 'error' => 'Description is required (max 500 characters).'];
        }

        if ($author !== '' && strlen($author) > 100) {
            return ['ok' => false, 'error' => 'Author must be at most 100 characters.'];
        }

        // Auto-generate namespace from name if not provided.
        if ($namespace === '') {
            $namespace = preg_replace('/[^a-zA-Z0-9]/', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        }

        if ($namespace === '' || !preg_match('/^[A-Z][a-zA-Z0-9]*$/', $namespace)) {
            return ['ok' => false, 'error' => 'Namespace must be PascalCase (e.g. MyExtension).'];
        }

        // Slug for snake_case (used in table names).
        $lowerSlug = str_replace('-', '_', $slug);

        // Pluralize slug (simple heuristic).
        $pluralSlug = str_ends_with($slug, 's') ? $slug . 'es' : $slug . 's';

        // PascalCase slug.
        $pascalSlug = str_replace('_', '', ucwords(str_replace('-', '_', $slug)));

        // Template variables.
        $replacements = [
            '{{name}}'       => $name,
            '{{NAME}}'       => strtoupper($name),
            '{{slug}}'       => $slug,
            '{{SLUG}}'       => strtoupper($slug),
            '{{lower_slug}}' => $lowerSlug,
            '{{namespace}}'  => $namespace,
            '{{NAMESPACE}}'  => $namespace,
            '{{version}}'    => $version,
            '{{VERSION}}'    => strtoupper($version),
            '{{description}}'=> $desc,
            '{{DESCRIPTION}}'=> strtoupper($desc),
            '{{author}}'     => $author ?: 'Anonymous',
            '{{AUTHOR}}'     => strtoupper($author ?: 'Anonymous'),
            '{{table}}'      => strtolower($lowerSlug),
            '{{TITLE}}'      => $name,
            '{{title}}'      => $name,
            '{{content}}'    => 'Content goes here.',
            '{{pascal_slug}}'=> $pascalSlug,
            '{{plural_slug}}'=> $pluralSlug,
        ];

        // Determine paths.
        $templatesDir = __DIR__ . '/../../../resources/scaffolds/' . $type;
        $stagingDir   = __DIR__ . '/../../../storage/extension-staging/' . $slug;

        if (!is_dir($templatesDir)) {
            return ['ok' => false, 'error' => 'Template directory not found for type: ' . $type];
        }

        if (is_dir($stagingDir)) {
            return ['ok' => false, 'error' => 'A scaffold already exists at /storage/extension-staging/' . $slug . '/'];
        }

        // Create staging directory.
        if (!mkdir($stagingDir, 0755, true)) {
            return ['ok' => false, 'error' => 'Failed to create staging directory.'];
        }

        // Read and process templates.
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($templatesDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $relPath = $file->getPathname();
            $relPath = str_replace($templatesDir . '/', '', $relPath);

            // Read file content.
            $content = file_get_contents($file->getPathname());

            if ($file->isFile()) {
                // Apply replacements.
                $content = str_replace(array_keys($replacements), array_values($replacements), $content);

                // Rename files with placeholders.
                $destRel = $relPath;
                foreach ($replacements as $placeholder => $value) {
                    if (strpos($destRel, $placeholder) !== false) {
                        $destRel = str_replace($placeholder, $value, $destRel);
                    }
                }

                $destPath = $stagingDir . '/' . $destRel;
                file_put_contents($destPath, $content);
                $files[] = $destRel;
            }
        }

        return ['ok' => true, 'files' => $files, 'slug' => $slug];
    }
}
