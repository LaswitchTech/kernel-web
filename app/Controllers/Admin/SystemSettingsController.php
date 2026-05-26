<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\SettingsRegistry;
use App\Models\AuditLogRepository;
use App\Models\SystemSettingRepository;
use App\Services\ConfigOverrideService;
use App\Services\SystemSettingService;

/**
 * Admin system settings controller.
 *
 * Routes:
 *   GET  /admin/settings  → show()    Display the settings form
 *   POST /admin/settings  → update()  Validate and save settings
 *
 * Both routes are protected by ['WebAuth', 'WebPermission:admin'].
 *
 * Core managed settings:
 *   app.name    Application display name
 *   app.url     Public-facing URL
 *
 * Plugins can register additional settings sections via SettingsRegistry::addSection().
 * Plugin keys must use the format `<plugin>.<key>` (enforced at registration).
 *
 * Validation rules:
 *   app.name    required, max 100 chars
 *   app.url     required, must start with http:// or https://, max 255 chars
 *
 * Sensitive values (passwords, secrets) are never displayed
 * or managed by core — plugins handle their own sensitive fields.
 */
class SystemSettingsController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /admin/settings
    // -------------------------------------------------------------------------

    public function show(array $params = []): void
    {
        [$viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $rawConfig = $this->container->get('config');
        $config    = $this->resolveAppConfig($rawConfig);
        $override  = new ConfigOverrideService();
        $local     = $override->readLocal();

        // Register developer section so toggle renders in /admin/settings.
        $this->registerDeveloperSection();

        $settings = [
            'app_name'                 => $config['name'] ?? 'Kernel-Web',
            'app_url'                  => $config['url'] ?? 'http://localhost',
            'developer.developer'      => ($local['developer']['developer'] ?? false) === true
                                          || ($config['developer'] ?? false) === true,
            'developer.debug'          => ($local['developer']['debug'] ?? false) === true
                                          || ($config['debug'] ?? false) === true,
            'developer.dev_console'    => ($local['developer']['dev_console'] ?? false) === true
                                          || ($config['dev_console'] ?? false) === true,
            'auth.two_factor.enforced' => ($rawConfig['auth']['two_factor']['enforced'] ?? false) === true,
        ];

        $sections = SettingsRegistry::getSections($permissions);

        $pageTitle     = 'Settings';
        $activeSection = '/admin/settings';
        $errors        = [];
        $flash         = $this->popFlash();

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Settings', 'url' => null],
        ];

        ob_start();
        require $viewsPath . '/admin/settings.php';
        $content = ob_get_clean();

        require $viewsPath . '/layouts/panel.php';
    }

    // -------------------------------------------------------------------------
    // POST /admin/settings
    // -------------------------------------------------------------------------

    public function update(array $params = []): void
    {
        [$viewsPath, $appName, $displayName, $permissions] = $this->ctx();

        $override = new ConfigOverrideService();

        $input = [
            'app.name' => trim($_POST['app_name'] ?? ''),
            'app.url'  => trim($_POST['app_url']  ?? ''),
        ];

        // Merge plugin keys from $_POST.
        $pluginSections = SettingsRegistry::getSections($permissions);
        foreach ($pluginSections as $section) {
            $keys = SettingsRegistry::getSectionKeys($section->id);
            foreach ($keys as $key) {
                $field = str_replace('.', '_', $key);
                if (isset($_POST[$field])) {
                    $input[$key] = trim($_POST[$field] ?? '');
                }
            }
        }

        // Developer section keys (core, no plugin prefix).
        $developerKeys = ['developer.developer', 'developer.debug', 'developer.dev_console'];
        $developerInput = [];
        foreach ($developerKeys as $key) {
            $field = str_replace('.', '_', 'developer.' . substr($key, strlen('developer.') + 1));
            // Developer section uses developer_developer / developer_debug / developer_dev_console.
            if (isset($_POST[$field])) {
                $developerInput[$key] = '1';
            }
        }

        // Core validation (uses form field names).
        $coreInput = [
            'app_name' => $input['app.name'],
            'app_url'  => $input['app.url'],
        ];
        $errors = $this->validateCore($coreInput);

        // Plugin section validation (uses dot-key names).
        foreach ($pluginSections as $section) {
            $keys = SettingsRegistry::getSectionKeys($section->id);
            if (empty($keys)) {
                continue;
            }
            $sectionInput = [];
            foreach ($keys as $key) {
                if (isset($input[$key])) {
                    $sectionInput[$key] = $input[$key];
                }
            }
            $sectionErrors = SettingsRegistry::validateSection($section->id, $sectionInput, $permissions);
            foreach ($sectionErrors as $field => $msg) {
                $errors[str_replace('.', '_', $field)] = $msg;
            }
        }

        if (!empty($errors)) {
            // Re-populate $settings from POST for the re-render.
            $settings = [
                'app_name' => $input['app.name'],
                'app_url'  => $input['app.url'],
            ];

            $pageTitle     = 'Settings';
            $activeSection = '/admin/settings';
            $flash         = null;

            $breadcrumbs = [
                ['label' => 'Administration', 'url' => '/admin'],
                ['label' => 'Settings', 'url' => null],
            ];

            ob_start();
            require $viewsPath . '/admin/settings.php';
            $content = ob_get_clean();

            http_response_code(422);
            require $viewsPath . '/layouts/panel.php';
            return;
        }

        // Persist core config to config/local.php (not DB).
        $override->set('app.name', $input['app.name']);
        $override->set('app.url', rtrim($input['app.url'], '/'));

        // Persist developer section to config/local.php.
        if (!empty($developerInput)) {
            $override->setBatch($developerInput);
        }

        // Persist plugin sections via ConfigOverrideService (file-backed config).
        foreach ($pluginSections as $section) {
            $keys = SettingsRegistry::getSectionKeys($section->id);
            if (empty($keys)) {
                continue;
            }
            $sectionInput = [];
            foreach ($keys as $key) {
                if (isset($input[$key])) {
                    $sectionInput[$key] = $input[$key];
                }
            }
            $dbSvc = new SystemSettingService(
                new SystemSettingRepository($this->container->get('db'))
            );
            SettingsRegistry::saveSectionViaConfig($section->id, $sectionInput, $override, $dbSvc);
        }

        $actorId = (int) ($this->container->get('principal')['user']['id'] ?? 0);
        $this->auditLog($actorId, 'settings.update', 'system_settings', 0, [
            'app_name' => $input['app.name'],
            'app_url'  => rtrim($input['app.url'], '/'),
        ]);

        $this->flash('success', 'System settings saved.');
        header('Location: /admin/settings');
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Validate core settings input (app.name, app.url).
     *
     * @return array<string, string>  Field → error message; empty = valid.
     */
    private function validateCore(array $input): array
    {
        $errors = [];

        if ($input['app_name'] === '') {
            $errors['app_name'] = 'Application name is required.';
        } elseif (strlen($input['app_name']) > 100) {
            $errors['app_name'] = 'Application name must be 100 characters or fewer.';
        }

        if ($input['app_url'] === '') {
            $errors['app_url'] = 'Application URL is required.';
        } elseif (!preg_match('#^https?://#i', $input['app_url'])) {
            $errors['app_url'] = 'Application URL must start with http:// or https://.';
        } elseif (strlen($input['app_url']) > 255) {
            $errors['app_url'] = 'Application URL must be 255 characters or fewer.';
        }

        return $errors;
    }

    /**
     * Build standard context variables for views.
     * Returns [$viewsPath, $appName, $displayName, $permissions].
     */
    private function ctx(): array
    {
        $rawConfig   = $this->container->get('config');
        $principal   = $this->container->get('principal');
        $config      = $this->resolveAppConfig($rawConfig);
        $viewsPath   = __DIR__ . '/../../Views';
        $appName     = $config['name'] ?? 'Kernel-Web';
        $displayName = ($principal['user']['display_name'] ?? '') !== ''
            ? $principal['user']['display_name']
            : $principal['user']['username'];
        $permissions = $principal['permissions'];

        return [$viewsPath, $appName, $displayName, $permissions];
    }

    /**
     * Write an audit log entry. Fails silently.
     */
    private function auditLog(
        ?int   $actorId,
        string $action,
        string $entityType,
        int    $entityId,
        array  $meta = []
    ): void {
        try {
            (new AuditLogRepository($this->container->get('db')))
                ->log($actorId, $action, $entityType, $entityId, $meta);
        } catch (\Throwable $e) {
            // Intentionally swallowed.
        }
    }

    /**
     * Write a flash message to the session.
     */
    private function flash(string $type, string $message): void
    {
        $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Resolve app config from flat or nested config arrays.
     *
     * Config::load('app') returns a flat array (name, url, debug, etc.).
     * Some callers pass nested config (['app' => [...]]). Handle both.
     */
    private function resolveAppConfig(array $config): array
    {
        if (is_array($config['app'] ?? null)) {
            return $config['app'];
        }
        return $config;
    }

    /**
     * AJAX endpoint for toggling auth.two_factor.enforced.
     *
     * POST /admin/settings/toggle
     * Body: { key: 'auth.two_factor.enforced', value: true|false }
     * Returns: { ok: bool, message: string, error?: string }
     */
    public function toggle2faEnforcement(array $params = []): void
    {
        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || !isset($payload['key'], $payload['value'])) {
            $this->jsonResponse(400, [
                'ok'    => false,
                'error' => 'Missing key or value.',
            ]);
        }

        $key   = $payload['key'];
        $value = $payload['value'];

        // Only allow known config keys.
        $allowedKeys = ['auth.two_factor.enforced'];
        if (!in_array($key, $allowedKeys, true)) {
            $this->jsonResponse(400, [
                'ok'    => false,
                'error' => "Unknown config key: {$key}",
            ]);
        }

        $normalized = ConfigOverrideService::normalizeFormValue($value);

        $override = new ConfigOverrideService();
        if (!$override->set($key, $normalized)) {
            $this->jsonResponse(500, [
                'ok'    => false,
                'error' => 'Failed to write config/local.php.',
            ]);
        }

        $this->jsonResponse(200, [
            'ok'     => true,
            'message' => "Config '{$key}' saved.",
        ]);
    }

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
            'saveConfig'   => function (array $input, \App\Contracts\ConfigWriterInterface $writer): void {
                foreach (['developer.developer', 'developer.debug', 'developer.dev_console'] as $key) {
                    if (isset($input[$key])) {
                        $writer->set($key, ConfigOverrideService::normalizeFormValue($input[$key]));
                    }
                }
            },
            'source' => 'core',
        ]);
    }
}
