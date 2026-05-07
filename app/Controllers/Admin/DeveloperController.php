<?php
namespace App\Controllers\Admin;

use App\Core\Controller;

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
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $config    = $this->container->get('config');
        $viewsPath = __DIR__ . '/../../Views';

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
