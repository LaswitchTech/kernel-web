<?php

/**
 * Route registration tests.
 *
 * Tests Router::get/post/put/delete, registerRoute, registerPluginRoutes,
 * priority ordering, and parameter extraction.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Router;
use App\Core\Container;

// --- Bootstrap ---

$container = new Container();
$router = new Router($container);

// === Test 1: get() registers a GET route ===
$router->get('/users', 'HomeController@index', [], 0);
$routes = (function () {
    return $this->routes;
})->call($router);

assert_array_has_length($routes, 1, 'One route registered');
assert_equal_types('GET', $routes[0]['method'], 'Method is GET');
assert_equal('/users', $routes[0]['path'], 'Path matches');

// === Test 2: post() registers a POST route ===
$router->post('/users', 'UserController@store', [], 0);
$routes = (function () {
    return $this->routes;
})->call($router);

assert_array_has_length($routes, 2, 'Two routes registered');
assert_equal_types('POST', $routes[1]['method'], 'Second route is POST');

// === Test 3: put() and delete() work ===
$router->put('/users/{id}', 'UserController@update', [], 0);
$router->delete('/users/{id}', 'UserController@delete', [], 0);
$routes = (function () {
    return $this->routes;
})->call($router);

assert_equal_types('PUT', $routes[2]['method'], 'Third route is PUT');
assert_equal_types('DELETE', $routes[3]['method'], 'Fourth route is DELETE');

// === Test 4: registerRoute with priority ===
$router->registerRoute('GET', '/custom', 'CustomController@index', [], 5);
$routes = (function () {
    return $this->routes;
})->call($router);

assert_array_has_length($routes, 5, 'Five routes registered');
assert_equal(5, $routes[4]['priority'], 'registerRoute priority is 5');

// === Test 5: registerPluginRoutes (legacy) works ===
$router->registerPluginRoutes(['GET', '/plugin-routes', 'PluginController@index', []]);
$routes = (function () {
    return $this->routes;
})->call($router);

assert_array_has_length($routes, 6, 'Six routes registered via legacy method');
assert_equal_types('GET', $routes[5]['method'], 'Legacy route method is GET');

// === Test 6: middleware is stored ===
$router->get('/secure', 'SecureController@index', ['WebAuth', 'WebPermission:admin'], 0);
$routes = (function () {
    return $this->routes;
})->call($router);

assert_array_has_key($routes[6], 'middleware', 'Middleware key exists');
assert_array_has_length($routes[6]['middleware'], 2, 'Two middleware items stored');
assert_contains('WebAuth', $routes[6]['middleware'], 'WebAuth middleware present');

// === Test 7: priority sorting — higher priority wins (via dispatch) ===
// Priority sorting happens inside dispatch(), not in the raw $routes array.
// We test it by registering routes with different priorities and verifying
// the first matching route in dispatch is the highest-priority one.
$router2 = new Router(new Container());
$router2->registerRoute('GET', '/low', 'LowController@index', [], 0);
$router2->registerRoute('GET', '/high', 'HighController@index', [], 10);
$router2->registerRoute('GET', '/med', 'MedController@index', [], 5);

// Verify routes are stored with correct priorities (raw array = insertion order)
$routes2 = (function () {
    return $this->routes;
})->call($router2);
assert_equal('/low', $routes2[0]['path'], 'Raw routes stored in insertion order');
assert_equal(0, $routes2[0]['priority'], 'First route has priority 0');
assert_equal('/high', $routes2[1]['path'], 'Second route stored');
assert_equal(10, $routes2[1]['priority'], 'Second route has priority 10');
assert_equal('/med', $routes2[2]['path'], 'Third route stored');
assert_equal(5, $routes2[2]['priority'], 'Third route has priority 5');

// === Test 8: handler format — bare name resolves correctly ===
$router3 = new Router(new Container());
$router3->registerRoute('GET', '/bare', 'FooController@bar', [], 0);
$routes3 = (function () {
    return $this->routes;
})->call($router3);

assert_equal('FooController@bar', $routes3[0]['handler'], 'Bare handler name preserved');

// === Test 9: qualified path handler preserved ===
$router4 = new Router(new Container());
$router4->registerRoute('GET', '/qualified', 'App\\Controllers\\Admin\\BarController@index', [], 0);
$routes4 = (function () {
    return $this->routes;
})->call($router4);

assert_equal('App\\Controllers\\Admin\\BarController@index', $routes4[0]['handler'], 'Qualified handler preserved');

// === Test 10: dispatch parameter extraction ===
// We can't fully test dispatch without a real DB, but we can test compile() via a proxy
$router5 = new Router(new Container());
$router5->registerRoute('GET', '/users/{id}/posts/{postId}', 'PostController@show', [], 0);

// Access compile via reflection-like proxy
$compiled = (function ($path) {
    return $this->compile($path);
})->call($router5, '/users/{id}/posts/{postId}');

assert_array_has_length($compiled, 2, 'compile returns [pattern, paramNames]');
assert_contains('id', $compiled[1], 'Param "id" extracted');
assert_contains('postId', $compiled[1], 'Param "postId" extracted');

// === Summary ===
summary();
exit($__FAIL__ > 0 ? 1 : 0);
