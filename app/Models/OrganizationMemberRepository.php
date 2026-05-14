<?php

namespace App\Models;

use App\Core\DatabaseInterface;

/**
 * Database operations for the organization_users pivot table.
 */
class OrganizationMemberRepository
{
    public function __construct(private DatabaseInterface $db) {}

    /**
     * Add a user to an organization.
     *
     * @param string $role One of: owner, admin, member, viewer.
     * @throws \RuntimeException On duplicate membership.
     */
    public function addMember(int $orgId, int $userId, string $role = 'member'): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO organization_users (organization_id, user_id, role, is_default, created_at)
             VALUES (?, ?, ?, 0, ?)',
            [$orgId, $userId, $role, $now]
        );
    }

    /**
     * Get all organizations a user belongs to.
     *
     * @return array<int, array{id: int, organization_id: int, user_id: int, role: string, is_default: int, created_at: string, name: string, slug: string, type: string, active: int}>
     */
    public function findOrgsForUser(int $userId): array
    {
        return $this->db->fetch(
            'SELECT ou.*, o.name, o.slug, o.type, o.active
             FROM organization_users ou
             JOIN organizations o ON o.id = ou.organization_id
             WHERE ou.user_id = ?
             ORDER BY ou.is_default DESC, o.name ASC',
            [$userId]
        );
    }

    /**
     * Get the default organization for a user (if any).
     */
    public function findDefaultOrgForUser(int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT ou.*, o.name, o.slug, o.type, o.active
             FROM organization_users ou
             JOIN organizations o ON o.id = ou.organization_id
             WHERE ou.user_id = ? AND ou.is_default = 1 AND o.active = 1
             LIMIT 1',
            [$userId]
        );
    }

    /**
     * Set the default organization for a user (unsets others first).
     */
    public function setDefaultOrg(int $userId, int $orgId): void
    {
        $now = date('Y-m-d H:i:s');
        // Unset current default
        $this->db->execute(
            'UPDATE organization_users SET is_default = 0, updated_at = ? WHERE user_id = ?',
            [$now, $userId]
        );
        // Set new default
        $this->db->execute(
            'UPDATE organization_users SET is_default = 1, updated_at = ? WHERE user_id = ? AND organization_id = ?',
            [$now, $userId, $orgId]
        );
    }

    /**
     * Check if a user is a member of an organization.
     */
    public function isMember(int $orgId, int $userId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM organization_users WHERE organization_id = ? AND user_id = ? LIMIT 1',
            [$orgId, $userId]
        ) !== null;
    }

    /**
     * Get the user's role within an organization.
     */
    public function getRole(int $orgId, int $userId): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT role FROM organization_users WHERE organization_id = ? AND user_id = ? LIMIT 1',
            [$orgId, $userId]
        );
        return $row['role'] ?? null;
    }

    /**
     * Remove a user from an organization.
     */
    public function removeMember(int $orgId, int $userId): void
    {
        $this->db->execute(
            'DELETE FROM organization_users WHERE organization_id = ? AND user_id = ?',
            [$orgId, $userId]
        );
    }

    /**
     * Remove all memberships for a user (cleanup on user deletion).
     */
    public function removeUserFromAllOrgs(int $userId): void
    {
        $this->db->execute('DELETE FROM organization_users WHERE user_id = ?', [$userId]);
    }

    /**
     * Get all users in an organization.
     *
     * @return array<int, array{id: int, user_id: int, organization_id: int, role: string, is_default: int, username: string, email: string, display_name: string}>
     */
    public function findMembers(int $orgId): array
    {
        return $this->db->fetch(
            'SELECT ou.*, u.username, u.email, u.display_name
             FROM organization_users ou
             JOIN users u ON u.id = ou.user_id
             WHERE ou.organization_id = ?
             ORDER BY ou.is_default DESC, u.display_name ASC',
            [$orgId]
        );
    }
}
