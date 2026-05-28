<?php

/**
 * Tasks plugin route registration.
 *
 * Routes are also declared in plugin.json and auto-processed by the kernel.
 * This file is included as a convenience for manual registration and testing.
 *
 * Priority: 1 (plugin routes override kernel routes at the same path).
 */

$router->get('/tasks', 'Plugins\Tasks\TaskController@index', ['WebAuth', 'WebPermission:tasks.manage', 'OrganizationScope'], 1);
$router->get('/tasks/create', 'Plugins\Tasks\TaskController@createForm', ['WebAuth', 'WebPermission:tasks.manage', 'OrganizationScope'], 1);
$router->post('/tasks', 'Plugins\Tasks\TaskController@store', ['WebAuth', 'WebPermission:tasks.manage', 'OrganizationScope'], 1);
$router->get('/tasks/{id}/edit', 'Plugins\Tasks\TaskController@editForm', ['WebAuth', 'WebPermission:tasks.manage', 'OrganizationScope'], 1);
$router->post('/tasks/{id}', 'Plugins\Tasks\TaskController@update', ['WebAuth', 'WebPermission:tasks.manage', 'OrganizationScope'], 1);
$router->post('/tasks/{id}/delete', 'Plugins\Tasks\TaskController@delete', ['WebAuth', 'WebPermission:tasks.manage', 'OrganizationScope'], 1);
