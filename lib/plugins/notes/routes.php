<?php

/**
 * Notes plugin — route registration.
 *
 * These routes are injected into the global router during plugin loading.
 * Each route uses the standard WebAuth middleware for session-based auth.
 *
 * Handler format: Plugins\{Plugin}\{Controller}@method
 * Resolves to: App\Plugins\{Plugin}\{Controller}
 */

// List notes for an entity (JSON API for AJAX consumers).
$router->get('/notes', 'Plugins\Notes\NotesController@index', ['WebAuth', 'OrganizationScope']);

// Create a note.
$router->post('/notes', 'Plugins\Notes\NotesController@add', ['WebAuth', 'OrganizationScope']);

// Show a single note (JSON API).
$router->get('/notes/{id}', 'Plugins\Notes\NotesController@show', ['WebAuth', 'OrganizationScope']);

// Delete a note.
$router->delete('/notes/{id}/delete', 'Plugins\Notes\NotesController@delete', ['WebAuth', 'OrganizationScope']);
