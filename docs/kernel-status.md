# Kernel-Web Core Status

## Purpose

This document tracks the state of the Kernel-Web core after the NetMon cleanup pass.
It answers: **what does the kernel contain today, what was removed, and what remains to be done.**

---

## What Kernel-Web Core Contains

### Kernel Infrastructure (Core)

| Layer | Contents |
|-------|----------|
| **Routing** | Central Router with wildcard parameter extraction and middleware chaining |
| **Service Container** | Simple DI container with `set/get/has` bindings |
| **Config System** | Hierarchical config with `local.php` override support |
| **Database Layer** | PDO abstraction (`DatabaseInterface` + `SQLiteDriver`), MySQL driver reserved |
| **Migration System** | Abstract `Migration` class + `MigrationRunner` |
| **Auth Foundation** | `AuthService` + `AuthProviderInterface` + `LocalAuthProvider` |
| **Permissions** | `Gate` class + `RequirePermission` middleware |
| **API Tokens** | `TokenService` + `TokenRepository` |
| **User Management** | Users, Groups, Permissions, GroupPermissions |
| **Logging** | `Logger` class with daily file rotation |
| **Error Handling** | `ErrorHandler` with debug/production modes |
| **Installer** | `InstallLock`, `EnvironmentChecker`, `DirectoryChecker`, `ConfigWriter` |
| **Env Loader** | `.env` file parser |

### Admin Controllers (Kernel-Level)

These controllers manage the core user/permission infrastructure:

- `AdminController` — admin dashboard
- `UserController` — user CRUD
- `GroupController` — group CRUD
- `PermissionController` — permission CRUD
- `SystemSettingsController` — system settings
- `LocationController` — hierarchical locations (kernel-level)

### Reusable Modules (Future Plugins)

These modules are generic enough to be extracted as plugins:

| Module | Description |
|--------|-------------|
| `Setup` | Installation wizard |
| `Notifications` | In-app notification inbox with channels (email, in-app, log) |
| `Chat` | Chat rooms and messaging |
| `Tasks` | Task management with scheduling and assignments |
| `FileManager` | File browser with preview support |
| `Notes` | Notes on arbitrary entities |

### Views

- Admin views (users, groups, permissions, settings, locations)
- Layouts (`layouts/app.php`)
- Auth views (login)
- Module views (chat, tasks, file-manager, notifications)
- Profile views
- Dashboard view

### Config Files

- `app.php` — app identity, debug, URL
- `auth.php` — auth provider, session config
- `database.php` — SQLite/MySQL config
- `notifications.php` — legacy dispatch channels
- `notifications-module.php` — module-level email channel
- `filemanager.php` — file roots configuration
- `local.php` — local overrides (not committed)

### Public Assets

- Bootstrap 5 + Bootstrap Icons
- Chart.js
- DataTables
- jQuery
- LESS stylesheets (app, components, themes)

---

## What Was Removed (NetMon-Specific)

### Controllers and Models

| Removed | Reason |
|---------|--------|
| `App\NetMon\Controllers\DeviceController` | Device CRUD |
| `App\NetMon\Controllers\AlertController` | Alert management |
| `App\NetMon\Controllers\DiscoveryController` | Network discovery |
| `App\NetMon\Controllers\MapController` | Topology map |
| `App\NetMon\Controllers\LogicalMapController` | Logical map |
| `App\NetMon\Controllers\TopologyMapController` | Topology map |
| `App\NetMon\Controllers\HomeController` | Dashboard (replaced by admin) |
| `App\NetMon\Models\DeviceRepository` | Device data access |
| `App\NetMon\Models\DeviceCheckRepository` | Device check data |
| `App\NetMon\Models\ServiceCheckRepository` | Service check data |
| `App\NetMon\Models\AlertRepository` | Alert data access |
| `App\NetMon\Models\DiscoveryRepository` | Discovery data |
| `App\NetMon\Models\DeviceLinkRepository` | Device link data |
| `App\NetMon\Models\DeviceLinkCandidateRepository` | Link candidate data |
| `App\Models\NetworkSegmentRepository` | Network segment data |
| `App\Controllers\Admin\NetworkSegmentController` | Network segment admin |
| `App\Controllers\Admin\TopologyCandidateController` | Topology candidate admin |

### Monitoring Infrastructure

| Removed | Reason |
|---------|--------|
| `App\Monitoring\Pinger` | ICMP ping (NetMon-specific) |
| `App\Monitoring\TcpChecker` | TCP port checking |
| `App\Monitoring\SubnetScanner` | Subnet scanning |
| `App\Monitoring\ArpResolver` | ARP resolution |
| `App\Services\TopologyCandidateGenerator` | Topology candidate generation |

### NetMon Routes Removed

- `/` — root dashboard
- `/devices/*` — device CRUD (18+ routes)
- `/alerts/*` — alert management (6 routes)
- `/discovery/*` — network discovery (13 routes)
- `/map/*` — topology/map views (3 routes)

### NetMon Migrations Removed (14 files)

| Migration | Reason |
|-----------|--------|
| `0009_create_devices_table` | Device table |
| `0010_add_merge_columns_to_devices` | Device merge support |
| `0011_create_device_interfaces_table` | Device interfaces |
| `0012_create_device_addresses_table` | Device addresses |
| `0013_migrate_device_host_to_addresses` | Host migration (device-specific) |
| `0014_create_device_checks_table` | Device check history |
| `0015_create_alerts_table` | Alert table |
| `0017_create_monitored_services_table` | Monitored services |
| `0018_create_service_checks_table` | Service check history |
| `0019_create_discovery_jobs_table` | Discovery job tracking |
| `0020_create_discovery_findings_table` | Discovery finding results |
| `0039_add_device_location_id` | Device location FK |
| `0040_create_network_segments` | Network segments |
| `0041_create_device_links_table` | Device topology links |
| `0042_create_device_link_candidates_table` | Topology link candidates |

### NetMon Seeds Removed (4 files)

- `DeviceSeed.php` — device test data
- `DiscoverySeed.php` — discovery test data
- `MonitoredServiceSeed.php` — service test data
- `MonitoringDataSeed.php` — monitoring history seed

### NetMon Scripts Removed (5 files)

- `monitor.php` — monitor worker (ping/check loop)
- `discover.php` — subnet discovery
- `generate-topology-candidates.php` — topology candidate generation
- `task-reminders.php` — NetMon task reminder script
- `config/monitoring.php` — monitoring retention config

### NetMon Views Removed

- `app/Views/devices/` — device CRUD views (9 files)
- `app/Views/alerts/` — alert views (2 files)
- `app/Views/discovery/` — discovery views (4 files)
- `app/Views/map/` — topology map views (3 files)
- `app/Views/admin/network-segment-create.php`
- `app/Views/admin/network-segment-edit.php`
- `app/Views/admin/network-segments.php`
- `app/Views/admin/topology-candidates.php`

---

## Deferred (Future Work)

### Modules to Extract as Plugins

These modules are generic but currently live inside the kernel.
They should become plugins in a future cleanup:

1. **Setup Module** (`Modules/Setup/`) — Installation wizard
2. **Notifications Module** (`Modules/Notifications/`) — Notification inbox
3. **Chat Module** (`Modules/Chat/`) — Chat rooms
4. **Tasks Module** (`Modules/Tasks/`) — Task management
5. **FileManager Module** (`Modules/FileManager/`) — File browser
6. **Notes Module** (`Modules/Notes/`) — Entity notes

### Plugin System Implementation

Implemented (v1 — minimal foundation):

- `app/Core/Plugins/PluginManifest.php` — manifest parsing and validation
- `app/Core/Plugins/PluginRegistry.php` — in-memory registry (discovered/invalid/enabled/disabled)
- `app/Core/Plugins/PluginLoader.php` — discovery, validation, dependency check
- `app/Core/Plugins/PluginException.php` — custom exception class
- `lib/plugins/` — plugin directory (with `.example-plugin` template)
- Integration in `public/index.php` — loads plugins during bootstrap

Extension points (deferred):

- Plugin route auto-registration
- Plugin migration auto-execution
- Plugin service auto-registration
- Plugin autoloader
- Plugin activation/deactivation API

### Modules to Extract as Plugins

These modules are generic but currently live inside the kernel.
They should become plugins in a future cleanup:

1. **Setup Module** (`Modules/Setup/`) — Installation wizard
2. **Notifications Module** (`Modules/Notifications/`) — Notification inbox
3. **Chat Module** (`Modules/Chat/`) — Chat rooms
4. **Tasks Module** (`Modules/Tasks/`) — Task management
5. **FileManager Module** (`Modules/FileManager/`) — File browser
6. **Notes Module** (`Modules/Notes/`) — Entity notes

### Future Kernel Work

- [ ] Extract remaining modules into plugins
- [ ] Theme loader implementation
- [ ] Layout renderer implementation
- [ ] MySQL/MariaDB driver
- [ ] OAuth provider abstraction
- [ ] Licensing system
- [ ] Extract remaining modules into plugins
- [ ] Add automated tests

---

## Migration History

Migrations `0001` through `0047` remain in the `database/migrations/` directory.

Gap in IDs (removed): `0009-0012`, `0013`, `0014`, `0015`, `0017-0018`, `0019-0020`, `0039-0040`, `0041-0042`

Gaps are intentional — they preserve the historical migration sequence.
Migration IDs will not be renumbered.

---

## Cleanup Checklist

### Completed in This Pass

- [x] Removed `App\NetMon` namespace
- [x] Removed `App\Monitoring` namespace
- [x] Removed `App\Services\TopologyCandidateGenerator`
- [x] Removed `config/monitoring.php`
- [x] Removed 5 NetMon-specific scripts
- [x] Removed 4 NetMon seed files
- [x] Removed 15 NetMon-specific migration files
- [x] Removed 46 NetMon-specific view files
- [x] Removed NetMon controllers (NetworkSegmentController, TopologyCandidateController)
- [x] Removed NetMon-specific repositories
- [x] Removed all NetMon routes from `routes/web.php`
- [x] Renamed branding ('NetMon' → 'Kernel-Web') in config, controllers, setup
- [x] Updated session name and database name defaults

### Not Yet Done

- [ ] Extract modules to `/lib/plugins/`
- [ ] Remove remaining NetMon references in `/docs`
- [ ] Update `DESIGN.md` with post-cleanup architecture
- [ ] Add automated tests
- [ ] MySQL/MariaDB driver
