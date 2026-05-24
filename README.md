<p align="center">
  <img src="public/assets/images/logo.png" alt="Kernel-Web Logo" width="200" />
</p>

# Kernel-Web
![License](https://img.shields.io/github/license/LaswitchTech/kernel-web?style=for-the-badge)
![GitHub repo size](https://img.shields.io/github/repo-size/LaswitchTech/kernel-web?style=for-the-badge&logo=github)
![GitHub top language](https://img.shields.io/github/languages/top/LaswitchTech/kernel-web?style=for-the-badge)

---

## Requirements

- **PHP 8.1+** (target: PHP 8.2)
- Composer (`wikimedia/less.php`)
- Web server with PHP support (Apache / Nginx)

> PHP 7.x is **not** supported. Kernel-Web uses modern PHP 8.x features (readonly, union types, mixed, match, constructor property promotion).

---

## Description

**Author**: Louis Ouellet

**Kernel-Web** is a modular, extensible PHP web application kernel designed to serve as a reusable foundation for building multiple web applications.

Instead of building applications from scratch, Kernel-Web provides:

- A structured backend architecture (routing, container, config, database layer)
- A foundation for authentication, permissions, and API tokens
- A modular plugin system for features
- A theme system for customization
- A layout system for UI reuse

Built with a strong focus on:
- Simplicity
- Modularity
- Long-term maintainability
- Reusability across multiple projects

---

## Why Kernel-Web?

Most web applications reinvent the same foundations:
- authentication
- routing
- file handling
- UI structure

Kernel-Web extracts these into a reusable core so new apps can be built faster, cleaner, and more consistently.

---

## Preview (Coming Soon)

---

## Core Concepts

### Kernel Core
Provides the foundation:
- Routing
- Service container
- Authentication (extensible)
- Plugin loader
- Theme loader
- Layout renderer
- Database abstraction

### Plugins
Extend functionality without modifying the core:
- Notes
- Chat
- File Manager
- Tasks
- Notifications
- Admin tools
- Future modules

### Themes
Control visual appearance using LESS variables and tokens.

### Layouts
Provide reusable UI structures (dashboard, auth pages, app shells, etc.).

---

## Features (Current & Planned)

### Core (Implemented)
- Lightweight PHP kernel (no heavy framework)
- Modular architecture
- Routing with middleware chaining
- Service container (DI)
- Config system with local overrides
- Database layer (SQLite, MySQL/MariaDB driver reserved)
- Migration system
- Authentication (users, groups, permissions, API tokens)
- Session and token-based auth

### Plugin System (Deferred)
- Installable and removable modules
- Plugin discovery and lifecycle
- Independent migrations and routes

### Theme System (Deferred)
- LESS-based theming
- Dark / Light mode support
- Token-based styling

### Layout System (Deferred)
- Reusable page structures
- Shared UI components across apps

### Licensing (Deferred)
- Support for licensed apps and plugins
- Future licensing server as first production app

---

## Architecture Highlights

- Kernel is application-agnostic (no domain-specific logic)
- Clear separation:
  - Controllers (orchestration)
  - Services (business logic)
  - Repositories (data access)
  - Kernel (infrastructure)
- No heavy framework dependency
- Plugin-first extensibility model
- Theme-driven UI system
- Layout reuse across applications

---

## Directory Structure

```text
/
├── app/
├── config/
├── data/
├── docs/
├── lib/
│   ├── plugins/
│   ├── themes/
│   └── layouts/
├── public/
├── resources/
├── routes/
├── storage/
├── vendor/
```

---

## Installation (Early Stage)

```bash
git clone https://github.com/LaswitchTech/kernel-web.git
cd kernel-web
```

> ⚠️ The project is currently in early development. Setup scripts and full installation steps will be added as the kernel stabilizes.

---

## Development

### Goals
- Build a reusable application kernel
- Keep core independent from application domain logic
- Maintain clear separation between kernel and features

### Workflow
- Follow rules in `CLAUDE.md`
- Follow architecture in `DESIGN.md`
- Document everything in `/docs`

---

## Roadmap

Short-term:
- Plugin system implementation
- Extract modules to `/lib/plugins/`
- Add automated tests

Mid-term:
- Theme system implementation
- Layout system implementation
- MySQL/MariaDB driver
- OAuth provider abstraction

Long-term:
- OAuth server/client support
- Licensing system
- Plugin marketplace
- Multi-app ecosystem

---

## Ecosystem (Planned)

Future apps built on Kernel-Web:
- Network monitoring application
- Licensing Server
- CRM / tools

---

## Security

Kernel-Web is designed with public deployments in mind:
- Only `/public` is web-accessible
- Sensitive directories are protected
- Server-side validation required for all inputs

---

## License

This project is distributed under the [GPLv3](LICENSE) license.

---

## Documentation

- `DESIGN.md` → architecture and system design
- `CLAUDE.md` → development workflow and rules
- `/docs` → implementation details and guides (in progress)

---

## Acknowledgments

- Inspired by long-term maintainable architectures
- Built as a foundation for multiple future applications
- Technologies used:
  - PHP
  - Bootstrap 5
  - LESS

---

## Status

### Development Status

Kernel-Web is **actively under development**. This repository documents a living kernel architecture in progress.

- Breaking changes may occur between commits during this phase.
- The plugin, theme, and layout APIs are not yet frozen.
- Authentication, configuration, and routing internals are still evolving.
- The codebase is functional but not yet considered stable production software.

Production deployments should be evaluated against these facts and used only with an understanding of these risks.

Contributors, testers, and reviewers are welcome. See `CLAUDE.md` and `DESIGN.md` for development guidance.

This repository will evolve into a full application platform over time.
