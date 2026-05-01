# Kernel-Web - Design Document

## Purpose

This document defines the **design decisions, architecture patterns, and structural conventions** of Kernel-Web.

It is separate from `CLAUDE.md`, which focuses on **workflow rules and development behavior**.

Use this file to:
- Document architectural decisions
- Define system design patterns
- Track evolving structure (plugins, themes, layouts, auth, etc.)
- Ensure consistency across implementations

---

## Design Principles

### 1. Kernel First
- The kernel must remain **application-agnostic**
- No domain-specific logic (e.g., NetMon) in core
- Everything reusable should live in kernel or plugins

### 2. Modular by Default
- Features must be:
  - Replaceable
  - Extendable
  - Optional
- Prefer plugins over core expansion

### 3. Explicit over Magic
- Avoid hidden behaviors
- Prefer readable, predictable flows
- No heavy frameworks

### 4. Separation of Concerns
- Controllers → orchestration only
- Services → business logic
- Repositories → data access
- Kernel → infrastructure
- Plugins → features

### 5. Secure by Design
- Assume public deployment
- Deny access by default
- Validate everything server-side

---

## High-Level Architecture

Kernel-Web is composed of 4 major layers:

```text
[ Kernel Core ]
      ↓
[ Plugins ]
      ↓
[ Layouts ]
      ↓
[ Themes ]
```

### Kernel Core
Provides:
- Routing
- Service container
- Auth system
- Plugin loader
- Theme loader
- Layout renderer
- Database layer
- Config system

### Plugins
Provide:
- Features (Notes, Chat, File Manager, etc.)
- Routes
- Services
- Migrations
- UI components

### Layouts
Provide:
- Page structure
- Reusable UI skeletons

### Themes
Provide:
- Visual styling
- LESS variables/tokens

---

## Directory Responsibilities

### `/app`
Core application logic (kernel only)

### `/lib/plugins`
Feature modules

### `/lib/themes`
Visual themes

### `/lib/layouts`
Reusable UI layouts

### `/public`
Only web-accessible directory

### `/storage`
Runtime data (logs, cache, uploads)

### `/data`
Persistent local data (SQLite)

---

## Plugin System Design

### Discovery
- Scan `/lib/plugins/*/plugin.json`
- Build registry

### Lifecycle
- Installed
- Enabled
- Disabled

### Boot Flow
1. Kernel loads config
2. Kernel discovers plugins
3. Validate dependencies
4. Register services
5. Register routes
6. Load migrations if needed

### Design Rules
- No plugin should break kernel if it fails
- Plugins must declare dependencies
- Plugins must be self-contained

---

## Theme System Design

### Goals
- Token-based styling
- Fully decoupled from components
- Easy theme switching

### Strategy
- LESS variables define theme
- Components consume variables only

### Rule
> Components must NEVER hardcode colors

---

## Layout System Design

### Purpose
Define reusable UI structures

### Example Regions
- Sidebar
- Topbar
- Content
- Footer
- Scripts

### Rule
> Layouts define structure, NOT behavior

---

## Authentication Design

### Core Concepts
- Users
- Groups
- Permissions
- Tokens

### Future Compatibility
- OAuth Server
- OAuth Client
- LDAP

### Rule
> Auth must be provider-agnostic

---

## Licensing Design (Planned)

### Goal
- Support paid plugins/apps

### Approach
- Central `LicensingService`
- Plugin-declared requirements

### Rule
> Licensing must NOT block development mode

---

## Routing Design

### Strategy
- Central router
- Plugin route injection

### Rule
> Routes must be declarative and traceable

---

## Service Container Design

### Goals
- Simple dependency injection
- No heavy framework

### Rule
> Services must be explicitly registered

---

## Database Design

### Default
- SQLite

### Future
- MySQL/MariaDB

### Rules
- Use PDO
- Use migrations
- Keep drivers isolated

---

## Security Design

### Web Root
- Only `/public` accessible

### Protection
- `.htaccess` blocks sensitive dirs

### Validation
- Always validate:
  - input
  - file paths
  - permissions

---

## Future Design Areas

To be expanded later:

- OAuth server design
- Plugin marketplace
- Theme marketplace
- Distributed app architecture
- Multi-instance auth sharing
- Remote agents

---

## Design Evolution Rule

Whenever a structural or architectural change is made:

1. Update this file
2. Update `/docs` if needed
3. Keep consistency across modules

---

## Relationship with CLAUDE.md

- `CLAUDE.md` → HOW to work
- `DESIGN.md` → WHAT we are building

Both must stay aligned.
