<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\ProfileModal;

/**
 * Profile Modal section API endpoints.
 *
 * Routes:
 *   GET  /api/profile/sections                  → index() — section metadata
 *   GET  /api/profile/sections/{id}             → content() — section HTML body
 */
class ProfileModalController extends Controller
{
    // ------
    // GET /api/profile/sections
    // ------

    public function index(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];

        if ($user === null) {
            $this->json(['error' => 'Authentication required'], 401);
            return;
        }

        $permissions = $principal['permissions'] ?? [];

        $this->json([
            'success'  => true,
            'sections' => ProfileModal::getMetadata($permissions),
        ]);
    }

    // ------
    // GET /api/profile/sections/{id}
    // ------

    public function content(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];

        if ($user === null) {
            $this->json(['error' => 'Authentication required'], 401);
            return;
        }

        $permissions = $principal['permissions'] ?? [];

        $id = (string) ($params['id'] ?? '');
        if ($id === '') {
            $this->json(['error' => 'Section ID is required'], 400);
            return;
        }

        $section = ProfileModal::getSection($id);
        if ($section === null) {
            $this->json(['error' => 'Section not found'], 404);
            return;
        }

        if (!$section->isVisible($permissions)) {
            $this->json(['error' => 'Section not available'], 403);
            return;
        }

        $context = [
            'principal'   => $principal,
            'permissions' => $permissions,
            'db'          => $this->container->get('db'),
        ];

        $html = $section->render($context);

        $this->json([
            'success' => true,
            'section' => $id,
            'html'    => $html,
        ]);
    }
}
