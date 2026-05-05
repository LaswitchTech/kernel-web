<?php

/**
 * Kernel-Web route definitions.
 *
 * Handler format: "Controller@method"
 *   - Qualified class names (e.g., 'App\Controllers\HomeController@index')
 *     resolve via the Router's auto-prefix logic.
 *   - Bare names (e.g., 'AdminController@index') resolve to 'App\Controllers\...'.
 *   - Module namespaces (e.g., 'Modules\Chat\Controllers\ChatController@index')
 *     resolve to 'App\Modules\Chat\Controllers\ChatController'.
 *
 * All kernel routes use priority 0 (lowest precedence) so plugins and
 * application overrides can replace them. See /docs/override-system.md.
 */

// -------------------------------- Public (no auth required) ------
// These routes have priority 0 by default (kernel = lowest precedence).
// A plugin can override any of these by registering the same path
// with priority > 0 (higher precedence).

$router->get('/', 'Controllers\Home\HomeController@index', [], 0);
$router->get('/install', 'Controllers\Home\HomeController@install', [], 0);
$router->get('/dashboard', 'Controllers\Home\HomeController@dashboard', [], 0);

// -------------------------------- CSS (public) ------
// Dynamically compiled CSS must be publicly accessible so unauthenticated
// pages (/, signin, setup) load styling correctly. No sensitive data
// is in the compiled output (only CSS custom property tokens and structural
// class definitions). Raw LESS files remain on disk — browsers request /css,
// not the source LESS files.
$router->get('/css', 'CssController@show', [], 0);

// -------------------------------- Admin Area (requires 'admin' permission) ------

// Admin dashboard
$router->get('/admin', 'Controllers\Admin\AdminController@index', ['WebAuth', 'WebPermission:admin']);
$router->get('/admin/audit', 'Controllers\Admin\AdminController@audit', ['WebAuth', 'WebPermission:admin']);
$router->get('/admin/settings', 'Controllers\Admin\SystemSettingsController@show', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/settings', 'Controllers\Admin\SystemSettingsController@update', ['WebAuth', 'WebPermission:admin']);

// Admin Extensions — read-only listing of discovered extensions.
$router->get('/admin/extensions', 'Controllers\Admin\ExtensionsController@index', ['WebAuth', 'WebPermission:extensions.manage']);
$router->get('/admin/extensions/catalog', 'Controllers\Admin\ExtensionsController@catalog', ['WebAuth', 'WebPermission:extensions.manage']);
$router->get('/admin/extensions/catalog/submit', 'Controllers\Admin\ExtensionsController@submitForm', ['WebAuth', 'WebPermission:extensions.manage']);
$router->post('/admin/extensions/catalog/submit', 'Controllers\Admin\ExtensionsController@handleSubmit', ['WebAuth', 'WebPermission:extensions.manage']);
$router->get('/admin/extensions/catalog/review', 'Controllers\Admin\ExtensionsController@review', ['WebAuth', 'WebPermission:extensions.manage']);
$router->post('/admin/extensions/catalog/{id}/approve', 'Controllers\Admin\ExtensionsController@handleApprove', ['WebAuth', 'WebPermission:extensions.manage']);
$router->post('/admin/extensions/catalog/{id}/reject', 'Controllers\Admin\ExtensionsController@handleReject', ['WebAuth', 'WebPermission:extensions.manage']);
$router->post('/admin/extensions/catalog/{id}/install', 'Controllers\Admin\ExtensionsController@handleInstall', ['WebAuth', 'WebPermission:extensions.manage']);
$router->post('/admin/extensions/catalog/{id}/enable', 'Controllers\Admin\ExtensionsController@handleEnable', ['WebAuth', 'WebPermission:extensions.manage']);
$router->post('/admin/extensions/catalog/{id}/disable', 'Controllers\Admin\ExtensionsController@handleDisable', ['WebAuth', 'WebPermission:extensions.manage']);
$router->post('/admin/extensions/catalog/{id}/uninstall', 'Controllers\Admin\ExtensionsController@handleUninstall', ['WebAuth', 'WebPermission:extensions.manage']);

// Admin Themes — preview page for theme development.
$router->get('/admin/themes/preview', 'Controllers\Admin\ThemeController@preview', ['WebAuth', 'WebPermission:admin']);

// Admin Developer — developer mode tools.
$router->get('/admin/developer', 'Controllers\Admin\DeveloperController@tools', ['WebAuth', 'WebPermission:admin']);

// Admin permissions — full CRUD.
$router->get('/admin/permissions', 'Controllers\Admin\PermissionController@index', ['WebAuth', 'WebPermission:admin']);
$router->get('/admin/permissions/create', 'Controllers\Admin\PermissionController@createForm', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/permissions', 'Controllers\Admin\PermissionController@store', ['WebAuth', 'WebPermission:admin']);
$router->get('/admin/permissions/{id}/edit', 'Controllers\Admin\PermissionController@editForm', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/permissions/{id}', 'Controllers\Admin\PermissionController@update', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/permissions/{id}/delete', 'Controllers\Admin\PermissionController@delete', ['WebAuth', 'WebPermission:admin']);

// Admin users — full CRUD + group assignment.
$router->get('/admin/users', 'Controllers\Admin\UserController@index', ['WebAuth', 'WebPermission:admin']);
$router->get('/admin/users/create', 'Controllers\Admin\UserController@createForm', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/users', 'Controllers\Admin\UserController@store', ['WebAuth', 'WebPermission:admin']);
$router->get('/admin/users/{id}/edit-account', 'Controllers\Admin\UserController@editAccountForm', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/users/{id}/account', 'Controllers\Admin\UserController@updateAccount', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/users/{id}/activate', 'Controllers\Admin\UserController@activate', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/users/{id}/deactivate', 'Controllers\Admin\UserController@deactivate', ['WebAuth', 'WebPermission:admin']);
$router->get('/admin/users/{id}/edit', 'Controllers\Admin\UserController@editForm', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/users/{id}', 'Controllers\Admin\UserController@update', ['WebAuth', 'WebPermission:admin']);

// Admin groups — CRUD routes.
$router->get('/admin/groups', 'Controllers\Admin\GroupController@index', ['WebAuth', 'WebPermission:admin']);
$router->get('/admin/groups/create', 'Controllers\Admin\GroupController@createForm', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/groups', 'Controllers\Admin\GroupController@store', ['WebAuth', 'WebPermission:admin']);
$router->get('/admin/groups/{id}/edit', 'Controllers\Admin\GroupController@editForm', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/groups/{id}', 'Controllers\Admin\GroupController@update', ['WebAuth', 'WebPermission:admin']);
$router->post('/admin/groups/{id}/delete', 'Controllers\Admin\GroupController@delete', ['WebAuth', 'WebPermission:admin']);

// -------------------------------- Chat Module ------
$router->get('/chat', 'Modules\Chat\Controllers\ChatController@index', ['WebAuth', 'WebPermission:chat.use']);
$router->get('/chat/rooms/create', 'Modules\Chat\Controllers\ChatController@createForm', ['WebAuth', 'WebPermission:chat.use']);
$router->post('/chat/rooms', 'Modules\Chat\Controllers\ChatController@store', ['WebAuth', 'WebPermission:chat.use']);
$router->post('/chat/rooms/{id}/join', 'Modules\Chat\Controllers\ChatController@join', ['WebAuth', 'WebPermission:chat.use']);
$router->post('/chat/rooms/{id}/messages', 'Modules\Chat\Controllers\ChatController@sendMessage', ['WebAuth', 'WebPermission:chat.use']);
$router->get('/chat/rooms/{id}', 'Modules\Chat\Controllers\ChatController@show', ['WebAuth', 'WebPermission:chat.use']);

// -------------------------------- File Manager Module ------
$router->get('/files', 'Modules\FileManager\Controllers\FileManagerController@index', ['WebAuth', 'WebPermission:files.manage']);
$router->get('/files/{rootId}/download', 'Modules\FileManager\Controllers\FileManagerController@download', ['WebAuth', 'WebPermission:files.manage']);
$router->get('/files/{rootId}/preview', 'Modules\FileManager\Controllers\FileManagerController@preview', ['WebAuth', 'WebPermission:files.manage']);
$router->get('/files/{rootId}/serve', 'Modules\FileManager\Controllers\FileManagerController@serve', ['WebAuth', 'WebPermission:files.manage']);
$router->post('/files/{rootId}/mkdir', 'Modules\FileManager\Controllers\FileManagerController@mkdir', ['WebAuth', 'WebPermission:files.manage']);
$router->post('/files/{rootId}/upload', 'Modules\FileManager\Controllers\FileManagerController@upload', ['WebAuth', 'WebPermission:files.manage']);
$router->post('/files/{rootId}/rename', 'Modules\FileManager\Controllers\FileManagerController@rename', ['WebAuth', 'WebPermission:files.manage']);
$router->post('/files/{rootId}/move', 'Modules\FileManager\Controllers\FileManagerController@move', ['WebAuth', 'WebPermission:files.manage']);
$router->post('/files/{rootId}/delete', 'Modules\FileManager\Controllers\FileManagerController@delete', ['WebAuth', 'WebPermission:files.manage']);
$router->get('/files/{rootId}', 'Modules\FileManager\Controllers\FileManagerController@browse', ['WebAuth', 'WebPermission:files.manage']);

// -------------------------------- Profile (user-owned settings) ------
$router->get('/profile', 'ProfileController@index', ['WebAuth']);
$router->post('/profile/notification-preferences', 'ProfileController@saveNotificationPreferences', ['WebAuth']);

// -------------------------------- Notifications Module ------
$router->get('/notifications', 'Modules\Notifications\Controllers\NotificationController@index', ['WebAuth']);
$router->post('/notifications/read-all', 'Modules\Notifications\Controllers\NotificationController@markAllRead', ['WebAuth']);
$router->post('/notifications/{id}/read', 'Modules\Notifications\Controllers\NotificationController@markRead', ['WebAuth']);

// JSON endpoints — SessionAuth returns 401 JSON on failure (not redirect).
$router->get('/api/notifications/count', 'Modules\Notifications\Controllers\NotificationController@unreadCount', ['SessionAuth']);
$router->get('/api/notifications/recent', 'Modules\Notifications\Controllers\NotificationController@recent', ['SessionAuth']);

// Chat JSON API — SessionAuth for AJAX callers.
$router->get('/api/chat/unread', 'Modules\Chat\Controllers\ChatController@unreadCount', ['SessionAuth']);

// -------------------------------- Authentication (public) ------
$router->get('/signin', 'AuthController@loginForm');
$router->post('/auth/login', 'AuthController@login');
$router->get('/auth/login', 'AuthController@loginRedirect');
$router->post('/auth/logout', 'AuthController@logout');
$router->get('/auth/me', 'AuthController@me', ['SessionAuth']);

// -------------------------------- Token Management ------
$router->get('/api/tokens', 'TokenController@index', ['SessionAuth']);
$router->post('/api/tokens', 'TokenController@create', ['SessionAuth']);
$router->delete('/api/tokens/{id}', 'TokenController@revoke', ['SessionAuth']);
