# Organizations Design

## Overview

Organizations represent a future optional concept for grouping users and data within Kernel-Web. They are not part of the kernel core — they are designed as a plugin/module.

## Conceptual Model

Organizations may represent:

- Internal organizations
- Prospects
- Clients
- Freight forwarders
- Customs brokers
- Customs offices
- Vendors
- Partners
- Other business entities

## Design Constraints

### Kernel Core Must Remain Organization-Agnostic

- Organizations must **NOT** be mandatory in kernel core
- The `users` table must NOT require organization columns at install time
- Auth system must remain compatible with optional organization scoping
- Core auth tokens, sessions, and permissions must work without organizations

### Plugin Responsibility

An organizations plugin should define:

- `organizations` table — the organization entities
- `organization_users` pivot table — many-to-many user membership
- `organization_roles` table (optional, future) — roles within an organization
- Optional `organization_id` columns on plugin tables — data ownership

```
lib/plugins/Organizations/
├── plugin.json
├── src/
│   ├── OrganizationRepository.php
│   ├── OrganizationService.php
│   └── OrganizationMigration.php
├── migrations/
│   └── 0001_create_organizations_table.php
└── views/
```

### Migration Path

1. **Phase 1 (current):** Kernel core without organizations
2. **Phase 2:** Organizations plugin design and foundation
3. **Phase 3:** Plugin implementation (tables, membership, roles)
4. **Phase 4:** Organization-level data scoping and middleware

## Open Design Questions

### Membership Model

- **Single organization per user** — simple, but limits cross-tenant scenarios
- **Multiple organizations per user** — supports multi-tenant use cases, requires pivot table (already planned)

### Roles

- Should organizations have built-in roles (admin, member, viewer)?
- Or should roles be managed through the existing permission system with organization-scoped permissions?

### Permissions

- Can a permission be scoped to an organization?
- Or does the plugin simply filter data based on the user's organization membership?

### Ownership

- Should plugin tables have an `organization_id` column?
- Should data ownership be explicit (`created_by` + `organization_id`) or implicit (inferred from user's default organization)?

### Scoping Location

- Should organization scoping happen in middleware (intercept queries globally)?
- Or should it be a repository pattern (callers explicitly scope queries)?

### Entity Classification

- Should prospects/clients/vendors be **organization types** (a `type` column on organizations)?
- Or should they be **plugin-specific classifications** (handled by individual plugins)?

### Auth Integration

- How does organization membership affect auth tokens?
- Can a user have a "default organization" active per session?
- Should session store include `organization_id` for easy scoping?

### Hierarchy

- Should organizations support parent/child relationships?
- Or should hierarchy be managed at the application level?

## Future Multi-Tenant Compatibility

Organizations design should be compatible with future multi-tenant behavior:

- Data scoping via `organization_id` naturally maps to tenant isolation
- Organization-level middleware can evolve into tenant-level middleware
- Auth tokens scoped to organizations can evolve into tenant-scoped tokens

## Design Rules

- Organizations are optional — kernel core must function without them
- Plugin tables must be able to exist without organization columns
- Organization scoping is a plugin responsibility, not a kernel responsibility
- Multi-tenant behavior is a future consideration, not a current requirement
