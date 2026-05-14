<?php

namespace App\Core;

use App\Models\OrganizationMemberRepository;
use App\Models\OrganizationRepository;

/**
 * Resolves the current organization context for an authenticated user.
 *
 * Reads from the PHP session and falls back gracefully when the membership
 * is revoked or the default org is deactivated.
 *
 * This class is kernel-neutral — it provides no automatic scoping.
 * Callers explicitly request the current org ID and apply it to queries.
 */
class OrganizationContext
{
    private ?int $cachedOrgId;
    private ?array $cachedInfo;

    public function __construct(
        private OrganizationMemberRepository $members,
        private OrganizationRepository $orgs,
    ) {}

    /**
     * Get the current user's default organization ID, or null if none.
     *
     * Cached per-request via the session key.
     */
    public function currentOrgId(): ?int
    {
        $this->cachedOrgId ??= $this->resolveDefault();
        return $this->cachedOrgId;
    }

    /**
     * Get full current organization info (row + membership), or null.
     */
    public function current(): ?array
    {
        $orgId = $this->currentOrgId();
        if ($orgId === null) {
            return null;
        }
        $this->cachedInfo ??= $this->loadInfo($orgId);
        return $this->cachedInfo;
    }

    /**
     * Switch the user's default organization.
     *
     * Validates that the user has membership before setting as default.
     *
     * @return bool True if the switch succeeded, false if not a member.
     */
    public function switch(int $targetOrgId): bool
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return false;
        }

        if (!$this->members->isMember($targetOrgId, $userId)) {
            return false;
        }

        $this->members->setDefaultOrg($userId, $targetOrgId);
        $this->cachedOrgId = $targetOrgId;
        $this->cachedInfo = $this->loadInfo($targetOrgId);

        return true;
    }

    /**
     * Resolve the default organization ID from session + DB.
     *
     * Priority:
     *   1. Session-stored org ID (validated against membership)
     *   2. DB default (is_default = 1)
     *   3. First active org by membership date (fallback)
     */
    private function resolveDefault(): ?int
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return null;
        }

        // 1. Session cache
        $sessionKey = 'org_default_' . $userId;
        $sessionOrgId = $_SESSION[$sessionKey] ?? null;
        if ($sessionOrgId !== null && $this->isValidMembership((int) $sessionOrgId, $userId)) {
            return (int) $sessionOrgId;
        }

        // 2. DB default
        $default = $this->members->findDefaultOrgForUser($userId);
        if ($default !== null && $default['active'] === 1) {
            $_SESSION[$sessionKey] = (string) $default['organization_id'];
            return (int) $default['organization_id'];
        }

        // 3. First active org
        $orgs = $this->members->findOrgsForUser($userId);
        foreach ($orgs as $row) {
            if ($row['active'] === 1) {
                $_SESSION[$sessionKey] = (string) $row['organization_id'];
                return (int) $row['organization_id'];
            }
        }

        return null;
    }

    /**
     * Validate that a user has active membership in an organization.
     */
    private function isValidMembership(int $orgId, int $userId): bool
    {
        if (!$this->members->isMember($orgId, $userId)) {
            return false;
        }
        // Also check org is active
        $org = $this->orgs->findById($orgId);
        return $org !== null && $org['active'] === 1;
    }

    /**
     * Load full org info for a given org ID.
     */
    private function loadInfo(int $orgId): ?array
    {
        $org = $this->orgs->findById($orgId);
        if ($org === null) {
            return null;
        }
        $org['_is_default'] = true;
        return $org;
    }

    /**
     * Clear cached state (for switching).
     */
    public function clearCache(): void
    {
        $this->cachedOrgId = null;
        $this->cachedInfo = null;
    }

    /**
     * Check if the user has any organization memberships.
     */
    public function hasAny(): bool
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return false;
        }
        $orgs = $this->members->findOrgsForUser($userId);
        return !empty($orgs);
    }

    /**
     * Count how many organizations the user belongs to.
     */
    public function count(): int
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return 0;
        }
        return count($this->members->findOrgsForUser($userId));
    }

    /**
     * Get the current user's ID from the session.
     */
    private function getUserId(): ?int
    {
        $user = $_SESSION['user'] ?? null;
        if ($user === null) {
            return null;
        }
        return (int) ($user['id'] ?? 0);
    }
}
