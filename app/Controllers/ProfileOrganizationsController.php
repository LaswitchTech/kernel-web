<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\ProfileModal;
use App\Models\OrganizationContext;
use App\Models\OrganizationMemberRepository;
use App\Models\OrganizationRepository;

/**
 * Profile Modal section for Organizations.
 *
 * API endpoints:
 *   GET  /api/profile/organizations                  → list()
 *   POST /api/profile/organizations/switch           → switchOrganization()
 *   POST /api/profile/organizations/create           → createOrganization()
 *
 * Section rendering:
 *   ProfileModal::renderSection('organizations', $context)
 */
class ProfileOrganizationsController extends Controller
{
    /**
     * GET /api/profile/organizations
     *
     * Returns the current user's organizations with default markers.
     */
    public function list(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];

        if ($user === null) {
            $this->json(['error' => 'Authentication required'], 401);
            return;
        }

        $db = $this->container->get('db');
        $repo = new OrganizationMemberRepository($db);
        $orgs = $repo->findOrgsForUser((int) $user['id']);

        $this->json([
            'organizations' => $orgs,
            'count'           => count($orgs),
        ]);
    }

    /**
     * POST /api/profile/organizations/switch
     *
     * Sets the given organization as the user's default.
     */
    public function switchOrganization(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];

        if ($user === null) {
            $this->json(['error' => 'Authentication required'], 401);
            return;
        }

        $orgId = (int) ($_POST['organization_id'] ?? 0);
        if ($orgId <= 0) {
            $this->json(['error' => 'organization_id is required'], 400);
            return;
        }

        $db = $this->container->get('db');
        $memberRepo = new OrganizationMemberRepository($db);
        $orgRepo = new OrganizationRepository($db);

        // Validate membership
        if (!$memberRepo->isMember($orgId, (int) $user['id'])) {
            $this->json(['error' => 'You are not a member of this organization.'], 403);
            return;
        }

        // Validate org is active
        $org = $orgRepo->findById($orgId);
        if ($org === null || $org['active'] === 0) {
            $this->json(['error' => 'This organization is no longer available.'], 410);
            return;
        }

        // Update membership default + session
        $memberRepo->setDefaultOrg((int) $user['id'], $orgId);
        $_SESSION['org_default_' . $user['id']] = (string) $orgId;

        // Invalidate context cache
        $sessionKey = 'org_context_' . $user['id'];
        unset($_SESSION[$sessionKey]);

        $this->json([
            'success'  => true,
            'organization_id' => $orgId,
        ]);
    }

    /**
     * POST /api/profile/organizations/create
     *
     * Creates a new organization for the current user.
     * The creator automatically becomes the owner (admin).
     */
    public function createOrganization(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user      = $principal['user'];

        if ($user === null) {
            $this->json(['error' => 'Authentication required'], 401);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->json(['error' => 'Organization name is required'], 400);
            return;
        }

        if (mb_strlen($name) > 100) {
            $this->json(['error' => 'Organization name must be 100 characters or less'], 400);
            return;
        }

        $type = $_POST['type'] ?? 'organization';
        $validTypes = ['organization', 'prospect', 'client', 'freight_forwarder', 'customs_broker', 'customs_office', 'vendor', 'partner'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'organization';
        }

        $db = $this->container->get('db');
        $repo = new OrganizationRepository($db);

        // Generate slug from name
        $slug = $this->generateSlug($name);
        $originalSlug = $slug;
        $counter = 1;

        while ($repo->isSlugTaken($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $orgId = $repo->create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
        ]);

        // Creator becomes admin + default member
        $memberRepo = new OrganizationMemberRepository($db);
        $memberRepo->addMember($orgId, (int) $user['id'], 'admin');
        $memberRepo->setDefaultOrg((int) $user['id'], $orgId);
        $_SESSION['org_default_' . $user['id']] = (string) $orgId;

        // Invalidate context cache
        $sessionKey = 'org_context_' . $user['id'];
        unset($_SESSION[$sessionKey]);

        $this->json([
            'success'          => true,
            'organization_id'  => $orgId,
            'name'             => $name,
            'slug'             => $slug,
            'is_default'       => true,
        ]);
    }

    /**
     * Render the section content for the Profile Modal.
     *
     * Called via ProfileModal::renderSection('organizations', $context).
     *
     * @param array<string, mixed> $context View variables
     * @return string HTML
     */
    public static function renderSection(array $context): string
    {
        $principal = $context['principal'] ?? null;
        $user      = $principal['user'] ?? null;
        if ($user === null) {
            return '';
        }

        $db = $context['db'] ?? null;
        if ($db === null) {
            return '';
        }

        $memberRepo = new OrganizationMemberRepository($db);
        $orgs = $memberRepo->findOrgsForUser((int) $user['id']);

        // Find the default org
        $defaultOrg = null;
        foreach ($orgs as $org) {
            if ($org['is_default'] == 1) {
                $defaultOrg = $org;
                break;
            }
        }

        // Also check the session cache
        $sessionKey = 'org_default_' . $user['id'];
        $sessionOrgId = $_SESSION[$sessionKey] ?? null;

        ob_start();
        ?>
        <div class="px-3 py-3">
            <!-- Current organization -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-muted mb-1">Current organization</label>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($defaultOrg !== null): ?>
                        <span class="badge bg-primary d-inline-flex align-items-center" id="pm-current-org-badge">
                            <i class="bi bi-building me-1"></i>
                            <span id="pm-current-org-name"><?= htmlspecialchars($defaultOrg['name']) ?></span>
                        </span>
                    <?php else: ?>
                        <span class="text-muted small" id="pm-current-org-name">— none —</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Switch organization -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-muted mb-1">Switch organization</label>
                <?php if (count($orgs) > 1): ?>
                    <select class="form-select form-select-sm" id="pm-org-switch" aria-label="Switch organization">
                        <option value="">Select an organization…</option>
                        <?php foreach ($orgs as $org): ?>
                            <option value="<?= (int) $org['organization_id'] ?>"
                                <?= $org['is_default'] == 1 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($org['name']) ?>
                                <?php if ($org['is_default'] == 1): ?>
                                    (default)
                                <?php endif; ?>
                                <?php if ($org['active'] == 0): ?>
                                    (inactive)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="pm-org-switch-status" class="small mt-1" style="display:none;"></div>
                <?php elseif (count($orgs) === 1): ?>
                    <p class="text-muted small mb-0">You only belong to one organization.</p>
                <?php else: ?>
                    <p class="text-muted small mb-0">You are not a member of any organization yet.</p>
                <?php endif; ?>
            </div>

            <!-- Create organization -->
            <div>
                <label class="form-label small fw-semibold text-muted mb-1">Create organization</label>
                <form id="pm-org-create-form" class="d-flex gap-2">
                    <input type="text"
                           class="form-control form-control-sm"
                           id="pm-org-name"
                           placeholder="Organization name"
                           maxlength="100"
                           autocomplete="off">
                    <button type="submit" class="btn btn-sm btn-primary" id="pm-org-create-btn">
                        <i class="bi bi-plus-lg me-1"></i>Create
                    </button>
                </form>
                <div class="form-text small">You will be added as the owner (admin).</div>
                <div id="pm-org-create-status" class="small mt-1" style="display:none;"></div>
            </div>
        </div>

        <script>
        (function () {
            var switchSelect = document.getElementById('pm-org-switch');
            var switchStatus = document.getElementById('pm-org-switch-status');
            var createForm = document.getElementById('pm-org-create-form');
            var createStatus = document.getElementById('pm-org-create-status');
            var orgNameInput = document.getElementById('pm-org-name');
            var currentBadge = document.getElementById('pm-current-org-badge');

            function showStatus(el, type, msg) {
                el.style.display = '';
                el.className = 'small mt-1 text-' + type;
                el.textContent = msg;
                if (type === 'danger' || type === 'success') {
                    setTimeout(function () { el.style.display = 'none'; }, 4000);
                }
            }

            function escHtml(s) {
                return String(s || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            // ── Switch organization ──
            if (switchSelect) {
                switchSelect.addEventListener('change', function () {
                    var val = parseInt(this.value, 10);
                    if (!val) return;

                    fetch('/api/profile/organizations/switch', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ organization_id: val }),
                    })
                    .then(function (r) {
                        if (!r.ok) throw new Error('Failed to switch');
                        return r.json();
                    })
                    .then(function (data) {
                        if (data.success) {
                            if (currentBadge) {
                                var nameEl = currentBadge.querySelector('#pm-current-org-name');
                                if (nameEl) nameEl.textContent = 'switching…';
                            }
                            // Reload section via AJAX
                            window.location.reload();
                        }
                    })
                    .catch(function () {
                        showStatus(switchStatus, 'danger', 'Failed to switch organization.');
                    });
                });
            }

            // ── Create organization ──
            if (createForm) {
                createForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var name = orgNameInput.value.trim();

                    if (!name) {
                        showStatus(createStatus, 'danger', 'Organization name is required.');
                        orgNameInput.focus();
                        return;
                    }

                    createForm.querySelector('#pm-org-create-btn').disabled = true;
                    createStatus.style.display = 'none';

                    fetch('/api/profile/organizations/create', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: name }),
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            createStatus.className = 'small mt-1 text-success';
                            createStatus.textContent = 'Created: ' + data.name;
                            createStatus.style.display = '';
                            orgNameInput.value = '';
                            setTimeout(function () {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showStatus(createStatus, 'danger', data.error || 'Failed to create.');
                            createForm.querySelector('#pm-org-create-btn').disabled = false;
                        }
                    })
                    .catch(function () {
                        showStatus(createStatus, 'danger', 'Request failed.');
                        createForm.querySelector('#pm-org-create-btn').disabled = false;
                    });
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Register this section with the Profile Modal.
     *
     * Call this during kernel boot (after profile sections are initialized).
     */
    public static function registerSection(): void
    {
        ProfileModal::addSection([
            'id'        => 'organizations',
            'label'     => 'Organizations',
            'icon'      => 'bi-building',
            'order'     => 30,
            'callback'  => [self::class, 'renderSection'],
            'permission'=> null, // visible to all authenticated users
            'source'    => 'core',
        ]);
    }

    // ------ Helpers ----------

    /**
     * Generate a URL-safe slug from a display name.
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
}
