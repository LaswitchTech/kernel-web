<?php

/**
 * Plugin manifest and lifecycle tests.
 *
 * Tests PluginManifest parsing, validation, PluginRegistry buckets,
 * and the loader's plugin.json discovery.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Plugins\PluginManifest;
use App\Core\Plugins\PluginRegistry;
use App\Core\Plugins\PluginException;

// === Test 1: Valid manifest creates correctly ===
$validData = [
    'name'        => 'test-plugin',
    'version'     => '1.2.3',
    'description' => 'Test plugin',
    'enabled'     => true,
];
$manifest = new PluginManifest($validData);

assert_equal('test-plugin', $manifest->name(), 'Plugin name is set');
assert_equal('1.2.3', $manifest->version(), 'Plugin version is set');
assert_equal('Test plugin', $manifest->description(), 'Description is set');
assert_true($manifest->enabled(), 'Enabled is true');
assert_equal([], $manifest->dependencies(), 'Empty dependencies');
assert_equal([], $manifest->permissions(), 'Empty permissions');
assert_equal([], $manifest->routes(), 'Empty routes');
assert_equal([], $manifest->migrations(), 'Empty migrations');
assert_equal([], $manifest->services(), 'Empty services');
assert_equal([], $manifest->hooks(), 'Empty hooks');
assert_equal([], $manifest->menus(), 'Empty menus');
assert_equal('', $manifest->minKernelVersion(), 'No kernel requirement');

// === Test 2: Missing name throws ===
assert_raises(
    fn() => new PluginManifest(['version' => '1.0.0']),
    PluginException::class,
    'Missing name throws PluginException'
);

// === Test 3: Missing version throws ===
assert_raises(
    fn() => new PluginManifest(['name' => 'test']),
    PluginException::class,
    'Missing version throws PluginException'
);

// === Test 4: Empty name throws ===
assert_raises(
    fn() => new PluginManifest(['name' => '', 'version' => '1.0.0']),
    PluginException::class,
    'Empty name throws PluginException'
);

// === Test 5: Empty version throws ===
assert_raises(
    fn() => new PluginManifest(['name' => 'test', 'version' => '']),
    PluginException::class,
    'Empty version throws PluginException'
);

// === Test 6: Default enabled is true ===
$minimalData = ['name' => 'minimal', 'version' => '0.0.1'];
$manifest2 = new PluginManifest($minimalData);
assert_true($manifest2->enabled(), 'Default enabled is true');

// === Test 7: Path can be set ===
$manifest3 = new PluginManifest(['name' => 'p', 'version' => '1.0.0']);
$manifest3->setPath('/lib/plugins/test');
assert_equal('/lib/plugins/test', $manifest3->path(), 'Path set correctly');
assert_equal('/lib/plugins/test', $manifest3->basePath(), 'Base path matches');

// === Test 8: Manifest with all optional fields ===
$fullData = [
    'name'        => 'full-plugin',
    'version'     => '2.0.0',
    'description' => 'Full plugin',
    'enabled'     => false,
    'requires'    => ['kernel' => '8.2'],
    'dependencies'=> ['plugin:notes' => '>=0.1.0'],
    'permissions' => ['full.manage', 'full.view'],
    'routes'      => [['GET', '/full', 'FullController@index', []]],
    'migrations'  => ['migrations/0001_test.php'],
    'services'    => ['full.service' => ['class' => 'App\\Plugins\\Full\\Service', 'singleton' => true]],
    'hooks'       => [['layout.head' => ['priority' => 5, 'content' => 'callback']]],
    'menus'       => [['menu' => 'sidebar', 'item' => ['name' => 'full']]],
    'lifecycle'   => ['install' => 'App\\Plugins\\Full\\Lifecycle@install'],
];
$manifestFull = new PluginManifest($fullData);
assert_false($manifestFull->enabled(), 'Enabled is false');
assert_equal('8.2', $manifestFull->minKernelVersion(), 'Kernel requirement set');
assert_equal('>=0.1.0', $manifestFull->dependencies()['plugin:notes'], 'Dependency set');
assert_array_has_length($manifestFull->permissions(), 2, 'Two permissions');
assert_array_has_length($manifestFull->routes(), 1, 'One route');
assert_array_has_length($manifestFull->migrations(), 1, 'One migration');
assert_array_has_key($manifestFull->lifecycle(), 'install', 'Lifecycle install hook key exists');

// === Test 9: Manifest with invalid dependency format ===
assert_raises(
    fn() => new PluginManifest(['name' => 'bad-dep', 'version' => '1.0.0', 'dependencies' => 'invalid']),
    PluginException::class,
    'Scalar dependencies throw PluginException'
);

// === Test 10: PluginRegistry — buckets are initially empty ===
$registry = new PluginRegistry();
assert_array_has_length($registry->getEnabled(), 0, 'Enabled bucket empty');
assert_array_has_length($registry->getDisabled(), 0, 'Disabled bucket empty');
assert_array_has_length($registry->getDiscovered(), 0, 'Discovered bucket empty');
assert_array_has_length($registry->getInvalid(), 0, 'Invalid bucket empty');
assert_false($registry->hasEnabled(), 'hasEnabled is false when empty');

// === Test 11: PluginRegistry — enable moves from discovered to enabled ===
$registry2 = new PluginRegistry();
$regManifest = new PluginManifest(['name' => 'reg-test', 'version' => '1.0.0']);
$registry2->addDiscovered($regManifest);
$registry2->enable('reg-test');

assert_array_has_length($registry2->getDiscovered(), 0, 'No longer in discovered');
assert_array_has_length($registry2->getEnabled(), 1, 'Now in enabled');
assert_true($registry2->isEnabled('reg-test'), 'isEnabled returns true');
assert_equal($regManifest, $registry2->getByName('reg-test'), 'getByName returns manifest');

// === Test 12: PluginRegistry — disable moves from enabled to disabled ===
$registry2->disable('reg-test');
assert_array_has_length($registry2->getEnabled(), 0, 'No longer enabled');
assert_array_has_length($registry2->getDisabled(), 1, 'Now in disabled');
assert_false($registry2->isEnabled('reg-test'), 'isEnabled returns false after disable');

// === Test 13: PluginRegistry — enable throws if not in discovered ===
assert_raises(
    fn() => $registry->enable('nonexistent'),
    PluginException::class,
 '  Enable nonexistent throws PluginException'
);

// === Test 14: PluginRegistry — addInvalid adds to invalid bucket ===
$invalidManifest = new PluginManifest(['name' => 'invalid-plug', 'version' => '1.0.0']);
$registry2->addInvalid($invalidManifest, 'Test reason');
assert_array_has_length($registry2->getInvalid(), 1, 'Invalid bucket has entry');
assert_contains('Test reason', $registry2->getInvalid()['invalid-plug']['reason'], 'Invalid reason stored');

// === Test 15: PluginRegistry — count includes all buckets ===
$registry3 = new PluginRegistry();
$reg1 = new PluginManifest(['name' => 'a', 'version' => '1.0.0']);
$reg2 = new PluginManifest(['name' => 'b', 'version' => '1.0.0']);
$reg3 = new PluginManifest(['name' => 'c', 'version' => '1.0.0']);
$reg4 = new PluginManifest(['name' => 'd', 'version' => '1.0.0']);
$registry3->addDiscovered($reg1);
$registry3->addDiscovered($reg2);
$registry3->addDiscovered($reg3);
$registry3->enable('a');
$registry3->disable('b');
$registry3->addInvalid($reg4, 'bad');
// enabled=1, disabled=1, discovered=1 (c), invalid=1 => count=4
assert_equal(4, $registry3->count(), 'Count includes all buckets');

// === Test 16: PluginRegistry — toArray on manifest ===
$array = $manifest->toArray();
assert_equal('test-plugin', $array['name'], 'toArray name');
assert_equal('1.2.3', $array['version'], 'toArray version');
assert_equal('enabled', $array['status'], 'toArray status');
assert_true(is_array($array['dependencies']), 'toArray dependencies is array');

// === Summary ===
summary();
exit($__FAIL__ > 0 ? 1 : 0);
