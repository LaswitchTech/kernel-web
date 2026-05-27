<?php

namespace App\Core;

/**
 * Abstract base for repositories that support organization-level data scoping.
 *
 * This interface defines the contract for repository methods that filter
 * queries by organization. It is intentionally lightweight — the kernel
 * provides no automatic scoping. Callers explicitly request scope.
 *
 * Implementation pattern:
 *   class MyEntityRepository extends OrganizationScopedRepository
 *   {
 *       public function findAll(): array
 *       {
 *           $where = $this->organizationWhere();
 *           $sql = 'SELECT * FROM my_entities';
 *           if ($where !== '') $sql .= ' ' . $where;
 *           return $this->db->fetch($sql);
 *       }
 *   }
 *
 * Design constraints:
 *   - Organizations are an optional plugin concept; the kernel does not
 *     mandate them. This base class is organization-agnostic — if no
 *     scope is set, queries return all rows (no WHERE clause).
 *   - Each plugin repository that needs scoping extends this class.
 */
abstract class OrganizationScopedRepository
{
    /**
     * Organization IDs to scope queries to.
     *
     * - null = "unscoped" (return all rows)
     * - [] = "scoped to none" (no rows)
     * - [1,2] = scoped to specific orgs
     */
    protected ?array $organizationIds = null;

    /**
     * Scope queries to a single organization.
     *
     * @return static
     */
    public function scopeOrganization(int $orgId): static
    {
        $this->organizationIds = [$orgId];
        return $this;
    }

    /**
     * Scope queries to a single organization (alias for fluent style).
     *
     * @return static
     */
    public function scopeOrgId(int $orgId): static
    {
        return $this->scopeOrganization($orgId);
    }

    /**
     * Scope queries to multiple organizations.
     *
     * @param array<int> $orgIds
     * @return static
     */
    public function scopeOrganizationIds(array $orgIds): static
    {
        $this->organizationIds = $orgIds;
        return $this;
    }

    /**
     * Clear any organization scope.
     *
     * @return static
     */
    public function unscoped(): static
    {
        $this->organizationIds = null;
        return $this;
    }

    /**
     * Check if this repository instance has an organization scope set.
     */
    public function isScoped(): bool
    {
        return $this->organizationIds !== null;
    }

    /**
     * Build a SQL WHERE clause for organization filtering.
     *
     * @param array<int> $ids Array of organization IDs to filter on.
     * @param string $tableAlias Optional table prefix (e.g. "e." → "e.organization_id IN (..)")
     * @param string $column Column name (default: "organization_id")
     * @return string SQL WHERE fragment (empty string if ids is empty → caller should handle "no rows" case)
     */
    public static function buildOrganizationWhereClause(
        array $ids,
        string $tableAlias = '',
        string $column = 'organization_id'
    ): string {
        if ($ids === []) {
            // Scoped to none — impossible to match any row.
            $prefix = $tableAlias !== '' ? rtrim($tableAlias, '.') . '.' : '';
            return "WHERE {$prefix}{$column} = 0";
        }

        $ids = array_map('intval', array_values($ids));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $prefix = $tableAlias !== '' ? rtrim($tableAlias, '.') . '.' : '';

        return "WHERE {$prefix}{$column} IN ({$placeholders})";
    }

    /**
     * Build the WHERE clause for the current instance scope.
     *
     * @param string $tableAlias Optional table prefix
     * @param string $column Column name
     * @return string SQL fragment (may be empty if unscoped)
     */
    protected function organizationWhere(
        string $tableAlias = '',
        string $column = 'organization_id'
    ): string {
        // If unscoped, return empty — caller appends directly to SQL.
        if ($this->organizationIds === null) {
            return '';
        }

        return static::buildOrganizationWhereClause(
            $this->organizationIds,
            $tableAlias,
            $column
        );
    }
}
