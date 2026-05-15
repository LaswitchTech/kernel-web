<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\OrganizationRepository;
use App\Models\OrganizationMemberRepository;
use App\Models\UserRepository;

/**
 * Admin organizations management page.
 *
 * Routes:
 *   GET  /admin/organizations          → index()     List all organizations
 *   POST /admin/organizations/{id}/toggle → toggle()  Toggle active/inactive
 *
 * Both routes are protected by ['WebAuth', 'WebPermission:admin'].
 */
class OrganizationsController extends Controller
{
    /**
     * GET /admin/organizations
     */
    public function index(array $params): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];
        $perms     = $principal['permissions'];

        $db      = $this->container->get('db');
        $orgRepo = new OrganizationRepository($db);
        $memberRepo = new OrganizationMemberRepository($db);
        $userRepo = new UserRepository($db);

        $allOrgs   = $orgRepo->findAll();
        $allUsers  = $userRepo->findAllActive();
        $pageTitle = 'Organizations';
        $activeSection = 'Admin Organizations';
        $appName   = $this->container->get('config')['name'] ?? 'Kernel-Web';
        $displayName = ($user['display_name'] ?? '') !== ''
            ? $user['display_name']
            : $user['username'];
        $permissions = $perms;

        $breadcrumbs = [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Organizations', 'url' => null],
        ];

        ob_start();
        require $this->viewsPath() . '/admin/organizations.php';
        $content = ob_get_clean();

        require $this->viewsPath() . '/layouts/panel.php';
    }

    /**
     * POST /admin/organizations/{id}/toggle
     */
    public function toggle(array $params): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];

        $id   = (int) ($params['id'] ?? 0);
        $db   = $this->container->get('db');
        $orgRepo = new OrganizationRepository($db);

        $org = $orgRepo->findById($id);
        if ($org === null) {
            $this->flash('error', 'Organization not found.');
            header('Location: /admin/organizations');
            exit;
        }

        $newActive = !$org['is_active'];
        $orgRepo->setActive($id, $newActive);

        $this->flash('success', sprintf(
            'Organization "%s" %s.',
            $org['name'],
            $newActive ? 'activated' : 'deactivated'
        ));
        header('Location: /admin/organizations');
        exit;
    }

    /**
     * Build standard context variables for views.
     */
    private function viewsPath(): string
    {
        return __DIR__ . '/../../Views';
    }
}
