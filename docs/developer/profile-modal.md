<?php

/**
 * Profile Modal — Developer Documentation
 *
 * Extensible user profile modal system. Replaces the navigation-based /profile
 * page pattern with a lightweight modal + API approach.
 *
 * ## Overview
 *
 * The profile modal provides a fast, non-navigating way for users to view
 * their account information and manage API tokens. Plugins can extend it
 * with additional tabbed sections.
 *
 * ## Files
 *
 * | File | Purpose |
 * |------|---------|
 * | `app/Views/partials/profile-modal.php` | Modal HTML template |
 * | `app/Views/partials/user-menu.php` | Profile trigger button |
 * | `app/Core/ProfileModal.php` | Section registry |
 * | `app/Core/ProfileModalSection.php` | Section value class |
 * | `app/Controllers/ProfileModalController.php` | Section API endpoints |
 * | `app/Controllers/AuthController.php` | `/api/profile` endpoint |
 * | `routes/web.php` | Route registration |
 * | `app/Auth/TokenService.php` | Token CRUD operations |
 * | `app/Controllers/TokenController.php` | Token API endpoints |
 *
 * ## Modal Structure
 *
 * The modal uses Bootstrap tabs inside the body:
 *
 * ```
 * +------------------------------------------+
 * | [Overview] [API Tokens] [Notifications]  |  ← tabs
 * +------------------------------------------+
 * | Overview tab content                      |
 * +------------------------------------------+
 * | [Close]                                   |
 * +------------------------------------------+
 * ```
 *
 * ### Core Tabs
 *
 * | Tab | Source | Content |
 * |-----|--------|---------|
 * | Overview | kernel | Username, email, display name, member since |
 * | API Tokens | kernel | Active tokens list, create, revoke |
 *
 * ### Plugin Tabs
 *
 * Plugins register tabs via `ProfileModal::addSection()`. The modal renders
 * them as additional tabs. Permission filtering happens server-side during
 * tab generation.
 *
 * ## API Endpoints
 *
 * ### GET /api/profile
 *
 * Returns safe user fields as JSON.
 *
 * **Response:**
 * ```json
 * {
 *   "user": {
 *     "id": 1,
 *     "username": "jdoe",
 *     "email": "jdoe@example.com",
 *     "display_name": "John Doe",
 *     "created_at": "20260101T120000Z",
 *     "updated_at": "20260102T120000Z"
 *   }
 * }
 * ```
 *
 * **Auth:** SessionAuth
 *
 * **Safe fields:** Only the fields listed above are returned. No `password_hash`,
 * `token`, or `secret` columns are ever exposed.
 *
 * ### GET /api/profile/sections
 *
 * Returns metadata for all visible sections (permission-filtered).
 *
 * **Response:**
 * ```json
 * {
 *   "success": true,
 *   "sections": [
 *     { "id": "overview", "label": "Overview", "icon": "bi-person", "order": 10, "source": "core" },
 *     { "id": "tokens", "label": "API Tokens", "icon": "bi-key", "order": 20, "source": "core" }
 *   ]
 * }
 * ```
 *
 * **Auth:** SessionAuth
 *
 * ### GET /api/profile/sections/{id}
 *
 * Returns the HTML body for a specific section. Lazy-loaded on first tab click.
 *
 * **Response:**
 * ```json
 * {
 *   "success": true,
 *   "section": "tokens",
 *   "html": "<div class=\"text-center py-5 text-muted\">...</div>"
 * }
 * ```
 *
 * **Error responses:**
 * - `401` — Not authenticated
 * - `404` — Section not found
 * - `403` — Section exists but user lacks permission
 *
 * **Auth:** SessionAuth
 *
 * ### Token Endpoints
 *
 * | Endpoint | Method | Auth | Description |
 * |------|----|------|-----------|
 * | `/api/profile/tokens` | GET | SessionAuth | List user's tokens |
 * | `/api/profile/tokens` | POST | SessionAuth | Create token |
 * | `/api/profile/tokens/{id}` | DELETE | SessionAuth | Revoke token |
 *
 * All token endpoints delegate to `TokenService`.
 *
 * ## Plugin Hook: profile.sections
 *
 * Plugins register sections by calling `ProfileModal::addSection()` directly
 * (e.g., in the plugin's `hooks.php` or a boot service).
 *
 * ### Registration
 *
 * ```php
 * ProfileModal::addSection([
 *     'id' => 'notifications',
 *     'label' => 'Notifications',
 *     'icon' => 'bi-bell',
 *     'order' => 20,
 *     'callback' => function () {
 *         return '<div>Notification preferences go here.</div>';
 *     },
 *     'permission' => 'profile.notifications',
 *     'source' => 'notifications',
 * ]);
 * ```
 *
 * ### Parameters
 *
 * | Parameter | Required | Description |
 * |-----------|----------|-------------|
 * | id | yes | Unique slug for the tab (regex: `^[a-z][a-z0-9_-]*$`) |
 * | label | yes | Tab display text |
 * | icon | no | Bootstrap Icons class (e.g., `bi-bell`) |
 * | order | no | Sort priority — lower renders first (default: 50) |
 * | callback | no | Closure returning HTML string (or null for no body) |
 * | permission | no | Required permission to see this tab |
 * | source | no | Plugin name or 'core' |
 *
 * **Note:** The `id` field replaces the former `name` parameter from the design doc.
 * The `callback` field replaces `content`. The new API is typed and validated.
 *
 * ## Security Rules
 *
 * 1. **Safe field filter** — only explicitly whitelisted fields returned
 * 2. **SessionAuth** — all endpoints require valid session
 * 3. **Token ownership** — token create/revoke verified against current user
 * 4. **Permission gating** — plugin sections hidden if user lacks permission
 * 5. **No sensitive storage** — modal JS never stores sensitive data in localStorage
 * 6. **Generic errors** — no stack traces or field names in error messages
 *
 * ## Security Rule Details
 *
 * ### Safe Field Filter
 *
 * The AuthController@profile method returns a hardcoded whitelist of fields:
 *
 * ```php
 * 'user' => [
 *     'id'         => (int)   ($user['id'] ?? 0),
 *     'username'   => (string)($user['username'] ?? ''),
 *     'email'      => (string)($user['email'] ?? ''),
 *     'display_name'=> (string)($user['display_name'] ?? ''),
 *     'created_at' => (string)($user['created_at'] ?? ''),
 *     'updated_at' => (string)($user['updated_at'] ?? ''),
 * ],
 * ```
 *
 * If new fields are added, review them against the security checklist:
 * - Could this field expose sensitive data if leaked?
 * - Is this field needed for the profile display?
 * - Should this field be in a plugin section instead?
 *
 * ### Token Ownership
 *
 * TokenService::revoke() verifies the requesting user owns the token:
 *
 * ```php
 * public function revoke(int $tokenId, int $userId): bool
 * ```
 *
 * The `$userId` parameter ensures a user can only revoke their own tokens.
 *
 * ## Extensibility Example
 *
 * A Notifications plugin adds a tab:
 *
 * ```json
 * // plugin.json
 * {
 *     "name": "notifications",
 *     "hooks": ["profile.sections"],
 *     "permissions": ["profile.notifications"]
 * }
 * ```
 *
 * ```php
 * // hooks.php
 * ProfileModal::addSection(
 *     name: 'notifications',
 *     label: 'Notifications',
 *     content: function () {
 *         return ob_get_clean() ?: '';
 *     },
 *     order: 20,
 *     permission: 'profile.notifications',
 *     source: 'notifications'
 * );
 * ```
 *
 * ```php
 * // views/profile/notifications.php
 * <div class="notification-section">
 *     <!-- notification preferences form -->
 * </div>
 * ```
 *
 * ## JavaScript Behavior
 *
 * 1. User clicks "Profile" button (`js-profile-trigger`)
 * 2. Bootstrap modal opens
 * 3. Overview loads immediately (already in DOM)
 * 4. Other tabs load via AJAX on first click, cached in DOM
 * 5. Error states display inline alerts
 *
 * ## Design Constraints
 *
 * - Modal must never crash the page — errors are caught and displayed
 * - Modal HTML must be valid Bootstrap markup
 * - No sensitive data in localStorage or cookies
 * - Plugin sections must not break the modal layout
 * - Modal must work with keyboard navigation (Escape to close)
 * - Modal must be accessible (ARIA attributes, focus trapping)
 * - Modal width should not exceed viewport on mobile
 *
 * ## Implementation Tasks (Not Yet Done)

These tasks are tracked in the Phase 2 section of ROADMAP.md.

1. ~~**Profile Modal tabbed UI foundation**~~ — convert static modal to tabs, wire Overview section to `/api/profile` — **done**
2. ~~**Profile Modal section registry / hook system**~~ — `ProfileModal` class, `profile.sections` hook registration — **done** (see docs below)
3. ~~**Profile Modal API Tokens section**~~ — integrate existing TokenController endpoints into the modal — **done**
4. **Profile Modal plugin-provided sections** — tab loading, permission gating

For design decisions, see DESIGN.md under "UI Design Standards → Profile Modal".
 */
