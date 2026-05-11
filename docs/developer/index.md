# Developer Documentation

## Kernel Core

- [Architecture](kernel/architecture.md) — High-level architecture and design decisions
- [Kernel Status](kernel/kernel-status.md) — Current development state and cleanup history
- [Service Design](kernel/services.md) — Service layer patterns and conventions
- [Schema Reference](kernel/schema.md) — Database schema reference
- [Domain Model](kernel/domain-model.md) — Core domain entities and relationships
- [Testing](testing.md) — Zero-dependency test framework, running tests, writing new suites

## Plugin Development

- [Notes Plugin](plugins/notes-plugin.md) — First real plugin, polymorphic annotations
- [Tasks Plugin](plugins/tasks-plugin.md) — Second real plugin, polymorphic task management
- [Notes Module (Legacy)](plugins/notes-module.md) — Legacy notes module documentation
- [Notifications Module (Legacy)](plugins/notifications-module.md) — Legacy notifications module documentation

## Layout & Theme Development

- [Theming](layouts/theming.md) — Theme system, LESS variables, and token-based styling

## Installation

- [Install Guide](installation/install.md) — Installation steps
- [Installer Architecture](installation/installer-architecture.md) — Installer design
- [Setup Wizard](installation/setup-wizard.md) — Setup wizard flow

## Reference

- [Routing Conventions](../routing.md) — Clean URL routing conventions
- [Override System](../override-system.md) — Route and layout override
- [Plugin Manifest Format](../DESIGN.md#plugin-system-design) — Plugin system design
- [Menu Registry](../menu-registry.md) — Menu registration system
- [Hook Registry](../layout-hook-registry.md) — Layout hook system
- [Root .htaccess](../root-htaccess.md) — Root .htaccess configuration
