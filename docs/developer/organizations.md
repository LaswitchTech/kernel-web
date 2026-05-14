# Organizations — Plugin-Scoped Multi-Tenant Foundation

## 1. Goals / Non-Goals

### Goals

- Define organizations as an **optional plugin**, never a kernel requirement
- Provide a data-scoping primitive: every organization-owned entity carries an `organization_id`
- Support **multiple organizations per user** via a pivot table (`organization_users`)
- Allow a user to have a **default organization** active in their session for easy scoping
- Keep the kernel core completely organization-agnostic — auth, permissions, and sessions work without the plugin
- Design for future evolution: organization-level middleware, plugin migration paths, SQLite/MySQL compatibility

### Non-Goals

- Full SaaS multi-tenant platform (no database-per-tenant, no shard routing, no tenant-level auth)
- Organization hierarchy (parent/child orgs) — out of scope for the first pass
- Organization-level RBAC beyond the pivot membership table (roles are deferred)
- Organization provisioning or self-service signup
- Organization billing, licensing, or isolation enforcement at the infrastructure level

## 2. Why Organizations Are Plugin-Scoped

The kernel must remain a reusable authentication and authorization backbone. Organizations are a business concept that belongs at the application or plugin layer for three reasons:

1. **Not all deployments need organizations.** A single-admin installation should install and run without any organization tables or configuration.
2. **Scoping rules vary by domain.** A customs-clearance app scopes data differently than a generic CMS. The plugin owns its scoping strategy.
3. **Lifecycle independence.** The plugin can be installed, updated, or uninstalled without touching kernel core. Its migrations run through the existing `MigrationRunner`.

This follows the same principle used for plugins, themes, and layouts: the kernel provides discovery, lifecycle, and registration primitives; domain-specific features live outside core.

## 3. Database Model

### Tables

**`organizations`**

| Column     | Type         | Notes                         |
|------------|--------------|-------------------------------|
| `id`       | INTEGER      | PRIMARY KEY AUTOINCREMENT     |
| `name`     | TEXT         | NOT NULL                      |
| `slug`     | TEXT         | UNIQUE, NOT NULL              |
| `type`     | TEXT         | DEFAULT 'organization'        |
| `active`   | BOOLEAN      | DEFAULT 1                     |
| `created_at`| DATETIME     |                               |
| `updated_at`| DATETIME     |                               |

Types: `organization`, `prospect`, `client`, `freight_forwarder`, `customs_broker`, `customs_office`, `vendor`, `partner`. The type column is a simple discriminator — the plugin validates it against a whitelist.

**`organization_users`**

| Column        | Type      | Notes                    |
|---------------|-----------|--------------------------|
| `id`          | INTEGER   | PRIMARY KEY AUTOINCREMENT|
| `organization_id` | INTEGER | FOREIGN KEY → organizations |
| `user_id`     | INTEGER   | FOREIGN KEY → users.id   |
| `role`        | TEXT      | DEFAULT 'member'         |
| `is_default`  | BOOLEAN   | DEFAULT 0, one per user  |
| `created_at`  | DATETIME  |                          |

Many-to-many pivot. A user may belong to multiple organizations. The `is_default` flag marks the active organization for session-based scoping. Enforced via UNIQUE(user_id, is_default) where is_default = 1 (partial index in MySQL, trigger or application enforcement in SQLite).

**`organization_roles`** (deferred, Phase 3)

| Column        | Type      | Notes                    |
|---------------|-----------|--------------------------|
| `id`          | INTEGER   | PRIMARY KEY AUTOINCREMENT|
| `organization_id` | INTEGER | FOREIGN KEY → organizations |
| `name`        | TEXT      | UNIQUE within org        |
| `permissions` | TEXT      | JSON array of permission codes |
| `created_at`  | DATETIME  |                          |

### Ownership Rules

- **Organizations are owned by their creator.** The `created_at` row in `organization_users` for the first user implicitly becomes the "owner" role.
- **Ownership is not a separate column.** It is derived from membership: the first user added (or the one with role `owner`) is the owner. This avoids duplicating ownership state.
- **Organization admins** (role `admin`) can manage members and settings. The `owner` can transfer ownership.
- **Kernel core never queries organizations.** It remains unaware of the tables.

## 4. Scoping Strategies

### Plugin-Level Scoping

The Organizations plugin provides:

1. **`OrganizationContext`** — a value object holding the current user's default organization ID (or null).
2. **`OrganizationScoper`** — a static helper that reads/writes the active org from the session.
3. **Repository extension interface** — plugins implement `OrganizationScopedRepository` which adds `scopeOrganization(int $orgId)` and `scopeUserOrgs(array $orgIds)` methods.

### Repository Pattern (Preferred)

Individual plugin repositories explicitly scope queries:

```php
class MyEntityRepository implements OrganizationScopedRepository
{
    private ?int $orgId = null;

    public function scopeOrganization(int $orgId): self
    {
        $this->orgId = $orgId;
        return $this;
    }

    public function findAll(): array
    {
        $sql = 'SELECT * FROM my_entities';
        if ($this->orgId !== null) {
            $sql .= ' WHERE organization_id = ' . $this->orgId;
        }
        return $this->db->query($sql);
    }
}
```

This is the default approach. It is explicit, testable, and does not hide side effects.

### Middleware / Context Provider (Optional)

A global middleware (`OrganizationMiddleware`) can:

1. Read the user's default organization from the session.
2. Inject `OrganizationContext` into the container or request.
3. Provide a `scope()` method that middleware or controllers call.

```php
// In a controller:
$orgId = OrganizationContext::current()?->id;
$entities = $repo->scopeOrganization($orgId)->findAll();
```

**Recommendation:** Start with repository pattern only. Add middleware later if cross-cutting scoping becomes tedious. The middleware should be opt-in, not required.

### Session Integration

The session stores `org_default_{userId}` → `organization_id`. When a user logs in:

1. Look up their `organization_users` rows where `is_default = 1`.
2. If none exists, default to their oldest membership.
3. Store in session under `org_default_{userId}`.
4. The session key format follows the existing kernel convention (`{prefix}_{userId}`).

## 5. Permission Model Interaction

### Kernel Permissions Remain Unchanged

The existing `permissions` and `group_permissions` tables are kernel-owned. Organizations do not modify them.

### Organization-Scoped Permissions

The Organizations plugin can optionally provide:

1. **`OrganizationPermissionChecker`** — checks permissions within a specific organization context:
   ```php
   $checker->userCan($userId, 'entities.create', $orgId);
   ```
   This extends the existing `Gate::userCan()` by adding an `organization_id` filter to the permission evaluation.

2. **Organization roles** (`organization_roles` table) — map groups of kernel permissions to org-specific roles. This is a convenience layer, not a replacement for kernel permissions.

### Recommendation

Keep kernel permissions as the source of truth. Organization roles are a **mapping layer** that says "the admin role in org X has permissions Y, Z." The plugin translates org roles to kernel permission codes during evaluation.

## 6. Auth / Session Interaction

### Login Flow

1. User authenticates via existing `AuthService::login()`.
2. After authentication, if the Organizations plugin is active, `AuthServiceProvider` (or a hook) runs `OrganizationScoper::resolveDefault($userId)`.
3. The default org is stored in the session. No kernel changes required.

### Auth Tokens

API tokens (`api_tokens` table) are kernel-owned and remain **organization-agnostic**. A token can access any org the user has access to. The org scoping is determined by:

1. The request's `X-Org-ID` header (explicit org selection).
2. The user's default organization (implicit).
3. Controllers can override via query parameter or body.

### Organization Switching

1. POST `/api/orgs/switch` with `organization_id`.
2. Validate user has membership in the target org.
3. Update `organization_users.is_default` for the user.
4. Update session `org_default_{userId}`.
5. Return success. No re-authentication needed.

### Inactive Organization

If an organization is deactivated (`active = 0`), members retain access but see a warning banner. If the user's default org is deactivated, their default falls back to their next active org.

## 7. Organization Switching UX

### Admin Panel

- **Organization listing page** at `/admin/orgs` — table view with search, filter by type/status.
- **Organization detail page** at `/admin/orgs/{id}` — members list, settings, metadata.
- **Member management** — add/remove users, change roles, set default.

### User Profile

- **"Active Organization" indicator** in the Profile Modal (similar to API Tokens section).
- Dropdown to switch default org (if user belongs to multiple).
- Visual indicator showing current org.

### Topbar

- Small org name badge next to the user avatar (only shown if user belongs to multiple orgs).
- Clicking opens a dropdown: "Switch organization" with list of user's orgs.

### Plugin UI Pattern

The Organizations plugin registers a Profile Modal section:

```php
ProfileModal::addSection([
    'id' => 'org',
    'label' => 'Organization',
    'icon' => 'bi-building',
    'order' => 10,
    'callback' => [OrgProfileSection::class, 'render'],
    'permission' => null, // visible to all authenticated users
    'source' => 'organizations',
]);
```

## 8. Migration Strategy for Existing Plugins

### Plugin Tables Without Organization Columns

Existing plugin tables **do not need** `organization_id` at install time. They can be migrated later:

1. Migration adds `organization_id` INTEGER column.
2. Backfill existing rows with `organization_id = NULL` (unscoped).
3. Application logic treats NULL as "public" or "unscoped."

### Migration for New Plugins

New plugins installed with the Organizations plugin active should:

1. Include `organization_id INTEGER REFERENCES organizations(id)` in their schema.
2. Index `organization_id` for query performance.
3. Document that queries must scope by `organization_id` when appropriate.

### Organizations Plugin Migration

The Organizations plugin's migration (e.g., `0001_create_organizations_tables.php`) runs when the plugin is installed/enabled. It creates:

1. `organizations` table
2. `organization_users` pivot table
3. Optionally `organization_roles` table (Phase 3)

The migration is idempotent (uses `CREATE TABLE IF NOT EXISTS`).

### Rollback

Uninstalling the plugin runs down-migrations in reverse order. The tables are dropped. Existing data in other plugins (with `organization_id` columns) becomes orphaned — this is acceptable for Phase 1. A future Phase 2 improvement: make `organization_id` columns nullable and add foreign key deferral.

## 9. SQLite / MySQL Compatibility

### Auto-Increment

- SQLite: `INTEGER PRIMARY KEY AUTOINCREMENT`
- MySQL: `BIGINT UNSIGNED AUTO_INCREMENT`
- The kernel's `DatabaseInterface` abstracts dialect differences. Use `getLastInsertId()` consistently.

### Partial Indexes

MySQL supports `WHERE` in indexes; SQLite < 3.15 does not. For `UNIQUE(user_id) WHERE is_default = 1`:

- **MySQL:** `UNIQUE INDEX idx_default(user_id) WHERE is_default = 1`
- **SQLite:** No partial index support. Use a trigger or application-level enforcement.

**Recommendation:** Use a BEFORE INSERT trigger for SQLite that ensures only one `is_default = 1` per user, plus application-level check. This avoids partial index portability issues.

### Boolean

- SQLite: stored as 0/1 INTEGER
- MySQL: stored as TINYINT(1)
- Read as truthy/falsy in PHP. No dialect-specific handling needed.

### DATETIME

- SQLite: TEXT in 'YYYY-MM-DD HH:MM:SS' format
- MySQL: DATETIME type
- Kernel's SQLiteDriver handles the conversion. Plugin code uses `date('Y-m-d H:i:s')`.

### Foreign Keys

- MySQL: `FOREIGN KEY` constraints enforced at DB level.
- SQLite: Foreign keys require `PRAGMA foreign_keys = ON` (enabled per-connection). The kernel's SQLite connection should have this enabled by default.

**Recommendation:** Store `PRAGMA foreign_keys = ON` in the SQLite connection setup. Document that plugin migrations should not assume FK enforcement at the DB level for SQLite.

## 10. Security Boundaries

### Kernel-Core Boundary

- **Kernel NEVER queries or references organization tables.** Auth, permissions, and sessions are organization-agnostic.
- Kernel users table has NO organization columns.
- Kernel API tokens have NO organization scope.

### Plugin Boundary

- The Organizations plugin is a **separate namespace** (`App\Plugins\Organizations\`).
- It registers its classes through the plugin's boot process, not kernel autoloading.
- It uses the existing `MigrationRunner` for schema changes.

### Data Access Boundary

- **All queries on organization-owned data MUST include an organization scope.** There are no "admin overrides" in Phase 1. Admins access data through their user membership in the organization.
- **NULL organization_id means "unscoped/public."** Controllers explicitly check for this.
- **Cross-organization queries are forbidden** unless explicitly requested by the admin (and gated by a permission).

### Session Security

- Session-stored `org_default_{userId}` is validated on every request against the `organization_users` table.
- If the stored org ID is invalid or the user has no membership, the default falls back gracefully.
- No CSRF concern: org switching is POST-only, gated by existing CSRF protection.

### Enumeration Safety

- Organization names/slugs are **not secret** (they are listed in an admin page).
- Membership enumeration is protected: if a user doesn't have access to an org, API responses do not reveal its existence (return 404, not 403).

## 11. Future Compatibility

### Marketplace / Distributed Systems

- Organization definitions are **plugin-owned**, so a future marketplace plugin could sync org data across instances.
- The `slug` field can serve as a stable identifier for cross-instance references.
- No API is defined yet for org sync, but the schema is compatible with RESTful endpoints.

### Multi-Instance Auth Sharing

- If two Kernel-Web instances share the same user database (distributed auth, Phase 4 of ROADMAP), organization membership is **per-instance**.
- Instance A's organizations are invisible to Instance B unless a sync plugin bridges them.
- The `organization_users.user_id` foreign key links to the shared `users` table, so the same user can have different org memberships across instances.

### OAuth / SSO

- OAuth providers are **organization-agnostic** in Phase 1.
- A future Phase 2 enhancement: OAuth `audience` or `tenant` claim could auto-assign the user to an org on first login.
- The `organization_users` table can store the source of membership (`invited`, `oauth_auto_assign`, `self_signup`).

### SaaS Multi-Tenancy Evolution

The current design is compatible with future SaaS multi-tenancy:

| Current Design          | SaaS Evolution                    |
|-------------------------|-----------------------------------|
| `organization_id` column | Becomes `tenant_id` for isolation |
| `organization_users`     | Becomes `tenant_memberships`      |
| Organization middleware  | Becomes tenant routing middleware  |
| `slug` field             | Becomes subdomain or route prefix  |
| Plugin-scoped scoping    | Becomes tenant-aware query builder |

The key insight: **`organization_id` is just a column.** If the scoping strategy needs to change (row-level, schema-level, database-level), the column already exists and the migration is in the scoping layer, not the schema.

## 12. Why This Is NOT Full SaaS Multi-Tenancy

Full SaaS multi-tenancy requires:

- **Database-level isolation** (separate databases or schemas per tenant)
- **Tenant-aware routing** (subdomain or path-based tenant resolution)
- **Resource quotas** (CPU, storage, API limits per tenant)
- **Tenant-level authentication** (users exist within a tenant context)
- **Cross-tenant security guarantees** (no data leakage between tenants)

This design provides **none** of those. Organizations are:

- **A data-scoping convention**, not an isolation mechanism
- **Stored in the same database** as kernel data
- **Enforced by application code**, not database constraints (for SQLite compatibility)
- **Optional and uninstallable** without kernel impact

This is intentional. The Organizations plugin is a **foundation** that *could* evolve toward SaaS multi-tenancy, but it does not implement it. The distinction matters because:

1. **Security posture:** Data isolation at the application level is weaker than database-level isolation. Admins must understand this boundary.
2. **Performance:** Shared-database scoping does not protect against resource contention (one org's queries affect all others).
3. **Compliance:** SaaS multi-tenancy often requires tenant-level audit logs, backups, and encryption. This design does not provide those.

If/when SaaS multi-tenancy is required, it should be a **separate plugin** that extends the Organizations schema with tenant-specific tables and middleware.

---

## File Structure

```
lib/plugins/Organizations/
├── plugin.json
├── src/
│   ├── OrganizationRepository.php
│   ├── OrganizationService.php
│   ├── OrganizationUserRepository.php
│   ├── OrganizationScoper.php
│   ├── OrganizationContext.php
│   └── OrganizationMigration.php
├── migrations/
│   └── 0001_create_organizations_tables.php
├── controllers/
│   ├── OrgsController.php          — Admin listing/detail
│   └── OrgApiService.php           — API endpoints (switch, list)
└── views/
    ├── orgs.php                    — Admin listing
    ├── org_detail.php              — Admin detail
    └── profile/
        └── org_section.php         — Profile Modal section
```

## Registry Pattern

The Organizations plugin follows the existing registry pattern (ProfileModal, SettingsRegistry, MenuRegistry):

- **`OrganizationRegistry`** (if needed) — for org-specific services, hooks, or extensions.
- **`ProfileModal::addSection()`** — registers the org section in the user profile.
- **`MenuRegistry::add()`** — registers admin menu items under an "Organizations" section.

No new kernel-level registry is required for Phase 1. The existing registries are sufficient.

## Testing Strategy

The Organizations plugin should include tests in the zero-dependency test framework:

```
tests/
  organizations_test.php    — Organization CRUD, membership, scoping
  org_scoper_test.php       — Session-based default org resolution
```

Use in-memory SQLite with the same `TestDB` wrapper pattern used in migration tests. Test:

1. Organization creation with slug uniqueness
2. Membership: add/remove user, multiple orgs per user
3. Default org resolution (session, fallback)
4. Org switching API
5. Scoping: queries correctly filter by org_id
6. Inactive org handling
7. Migration idempotency (CREATE TABLE IF NOT EXISTS)

---

## Open Questions for Future Phases

1. **Organization roles granularity** — should roles support custom permission sets, or only predefined roles (owner, admin, member, viewer)?
2. **Organization hierarchy** — parent/child relationships for freight forwarder/broker pairings?
3. **Organization branding** — custom colors/logos per org (white-label)?
4. **Organization API keys** — org-level API tokens separate from user tokens?
5. **Data export** — can an owner export all org data?
6. **Audit logging** — track org-level actions (member changes, settings changes)?
