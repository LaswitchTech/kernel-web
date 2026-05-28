<?php

namespace App\Core;

/**
 * Abstract base for repositories that support organization-level data scoping.
 *
 * Implementation pattern:
 *   class MyEntityRepository extends OrganizationScopedRepository
 *   {
 *       public function findAll(array $params = []): array
 *       {
 *           $orgParams = $this->organizationParams();
 *           $where     = $this->organizationWhere();
 *           $sql       = 'SELECT * FROM my_entities';
 *           if ($where !== '') $sql .= ' ' . $where;
 *           return $this->db->fetch($sql, array_merge($params, $orgParams));
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
     * Apply organization scope from the DI container.
     *
     * Reads the 'org_scope' binding (int|null) set by OrganizationScope
     * middleware. Manual scopeOrganization() calls override this value.
     *
     * @return static
     */
    public function scopeFromContainer(\App\Core\Container $container): static
    {
        $orgId = null;
        try {
            $orgId = $container->get('org_scope');
        } catch (\RuntimeException $e) {
            // 'org_scope' not bound — unscoped.
        }
        if ($orgId !== null && $orgId > 0) {
            $this->organizationIds = [(int) $orgId];
        }
        return $this;
    }

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
     * Get the current organization IDs for use as query parameters.
     *
     * Returns an array of parameter values (e.g. [1, 2]) that correspond
     * to the ? placeholders in the WHERE clause. When unscoped, returns [].
     */
    public function organizationParams(): array
    {
        if ($this->organizationIds === null) {
            return [];
        }
        return array_map('intval', array_values($this->organizationIds));
    }

    /**
     * Build the WHERE clause for the current instance scope.
     *
     * @param string $tableAlias Optional table prefix
     * @param string $column Column name
     * @return string SQL WHERE fragment (empty string if unscoped)
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

    /**
     * Build an AND clause for queries that already have a WHERE clause.
     *
     * @param string $tableAlias Optional table prefix
     * @param string $column Column name
     * @return string SQL fragment (empty string if unscoped)
     */
    protected function organizationAnd(
        string $tableAlias = '',
        string $column = 'organization_id'
    ): string {
        if ($this->organizationIds === null) {
            return '';
        }

        $fragment = static::buildOrganizationWhereClause(
            $this->organizationIds,
            $tableAlias,
            $column
        );

        // Replace leading "WHERE" with "AND" (case-insensitive).
        return preg_replace('/^\s*WHERE\b/i', 'AND', $fragment);
    }
}
