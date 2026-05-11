<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\SettingsRegistry;
use App\Models\AuditLogRepository;
use App\Models\SystemSettingRepository;
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

        $service = $this->service();

        $settings = [
            'app_name' => $service->getString('app.name'),
            'app_url'  => $service->getString('app.url'),
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

        $service = $this->service();

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

        // Persist core
        $service->set('app.name', $input['app.name']);
        $service->set('app.url',  rtrim($input['app.url'], '/'));

        // Persist plugin sections
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
            SettingsRegistry::saveSection($section->id, $sectionInput, $service);
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
     * Instantiate the service with fresh repository.
     */
    private function service(): SystemSettingService
    {
        return new SystemSettingService(
            new SystemSettingRepository($this->container->get('db'))
        );
    }

    /**
     * Build standard context variables for views.
     * Returns [$viewsPath, $appName, $displayName, $permissions].
     */
    private function ctx(): array
    {
        $principal   = $this->container->get('principal');
        $config      = $this->container->get('config');
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
}
