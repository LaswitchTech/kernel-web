<p align="center">
  <img src="public/assets/images/logo.png" alt="Kernel-Web Logo" width="200" />
</p>

# Kernel-Web
![License](https://img.shields.io/github/license/LaswitchTech/kernel-web?style=for-the-badge)
![GitHub repo size](https://img.shields.io/github/repo-size/LaswitchTech/kernel-web?style=for-the-badge&logo=github)
![GitHub top language](https://img.shields.io/github/languages/top/LaswitchTech/kernel-web?style=for-the-badge)

---

## Description

**Author**: Louis Ouellet

**Kernel-Web** is a modular, extensible PHP web application kernel designed to serve as a reusable foundation for building multiple web applications.

Instead of building applications from scratch, Kernel-Web provides:

- A structured backend architecture
- A plugin system for features
- A theme system for customization
- A layout system for UI reuse
- A foundation for authentication, APIs, and future integrations

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

### Core
- Lightweight PHP kernel (no heavy framework)
- Modular architecture
- Service-based backend structure
- SQLite support (MySQL/MariaDB planned)

### Plugin System (Planned)
- Installable and removable modules
- Plugin discovery and lifecycle
- Independent migrations and routes

### Theme System (Planned)
- LESS-based theming
- Dark / Light mode support
- Token-based styling

### Layout System (Planned)
- Reusable page structures
- Shared UI components across apps

### Authentication (Planned)
- Users, groups, permissions
- API tokens
- Future OAuth support (client + server)

### Licensing (Planned)
- Support for licensed apps and plugins
- Future licensing server as first production app

---

## Architecture Highlights

- Modular design
- Clear separation:
  - Controllers
  - Services
  - Repositories
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
- Extract reusable logic from NetMon
- Keep core independent from domain logic

### Workflow
- Follow rules in `CLAUDE.md`
- Follow architecture in `DESIGN.md`
- Document everything in `/docs`

---

## Roadmap

Short-term:
- Kernel bootstrap (entry point, routing, container)
- Secure public structure (`/public` + `.htaccess`)
- Config system
- Database layer and migrations

Mid-term:
- Plugin system implementation
- Theme system implementation
- Layout system implementation
- Authentication foundation

Long-term:
- OAuth server/client support
- Licensing system
- Plugin marketplace
- Multi-app ecosystem

---

## Ecosystem (Planned)

Apps built on Kernel-Web:
- NetMon (network monitoring)
- Licensing Server
- Future CRM / tools

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

🚧 Active development — Kernel architecture in progress

This repository will evolve into a full application platform over time.
