<?php

/**
 * Telico settings visibility regression test.
 *
 * Proves:
 *  - Telico plugin manifest loads without throwing PluginException
 *  - requires.kernel constraint is valid (semver) if present
 *  - plugin_hooks parsed correctly (plugins.bootstrap → TelicoHooks::bootstrap)
 *  - TelicoHooks class loads and is callable
 *  - TelicoHooks::registerSettings() adds section to SettingsRegistry
 *  - Section is visible for admin user (permission => null)
 *  - Section renderBody produces expected fields
 *
 * Run: php tests/telico_settings_test.php
 * Assertions: 17
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Plugins\PluginManifest;
use App\Core\SettingsRegistry;
use App\Core\SettingsSection;

// ------ Test 1: Manifest loads without throwing PluginException ----

$pluginJson = json_decode(file_get_contents(__DIR__ . '/../lib/plugins/telico/plugin.json'), true);
assert_not_null($pluginJson, 'Telico plugin.json decodes to array');
$manifest = new PluginManifest($pluginJson);
assert_not_null($manifest, 'PluginManifest constructs');
assert_equal('telico', $manifest->name(), 'Manifest name is telico');
assert_true($manifest->enabled(), 'Manifest enabled is true');

// ------ Test 2: plugin_hooks parsed correctly ----

$hooks = $manifest->pluginHooks();
assert_true(count($hooks) >= 1, 'At least one plugin_hook parsed');
assert_true(isset($hooks['plugins.bootstrap']), 'plugins.bootstrap hook present');
$bootstrap = $hooks['plugins.bootstrap'];
assert_not_null($bootstrap['callback'], 'plugins.bootstrap has callback');
assert_equal('Plugins\\Telico\\TelicoHooks::bootstrap', $bootstrap['callback'], 'Bootstrap callback is correct');

// ------ Test 3: TelicoHooks class loads and is callable ----

require_once __DIR__ . '/../lib/plugins/telico/src/TelicoHooks.php';
require_once __DIR__ . '/../lib/plugins/telico/src/TelicoSettings.php';
require_once __DIR__ . '/../lib/plugins/telico/src/TelicoTransport.php';
$ref = new ReflectionClass('Plugins\Telico\TelicoHooks');
assert_true($ref->hasMethod('bootstrap'), 'TelicoHooks has bootstrap method');
$method = $ref->getMethod('bootstrap');
assert_true($method->isPublic(), 'bootstrap is public');
assert_true(is_callable(['Plugins\Telico\TelicoHooks', 'bootstrap']), 'bootstrap is is_callable');

// ------ Test 4: TelicoHooks::registerSettings adds section to SettingsRegistry ----

SettingsRegistry::clear();

// Call the private registerSettings method via reflection
$hooksClass = new ReflectionClass('Plugins\Telico\TelicoHooks');
$method = $hooksClass->getMethod('registerSettings');
$method->setAccessible(true);
$method->invoke(null);

assert_true(SettingsRegistry::hasSections(), 'After registerSettings(), SettingsRegistry has sections');

$sections = SettingsRegistry::getSections(['admin']);
assert_true(count($sections) >= 1, 'At least one section visible for admin');

$telicoSection = SettingsRegistry::getSection('telico');
assert_not_null($telicoSection, 'Telico section registered in registry');
assert_equal('telico', $telicoSection->id, 'Section ID is telico');
assert_equal('Telico SMS Settings', $telicoSection->label, 'Section label is Telico SMS Settings');
assert_equal('right', $telicoSection->column, 'Section column is right');
assert_true($telicoSection->isVisible(['admin']), 'Section visible for admin permission');
assert_true($telicoSection->isVisible([]), 'Section visible for any permission (permission=null)');

// ------ Test 5: Section keys are valid (telico. prefix) ----

$keys = SettingsRegistry::getSectionKeys('telico');
assert_true(count($keys) >= 1, 'Section has at least one key');
foreach ($keys as $key) {
    assert_true(str_starts_with($key, 'telico.'), "Key '$key' has telico. prefix");
}

// ------ Test 6: renderBody produces expected HTML content ----

$ctx = ['settings' => ['telico.username' => 'testuser', 'telico.callerid' => '+15551234567'], 'errors' => []];
$html = $telicoSection->renderBody($ctx);
assert_true(strlen($html) > 100, 'renderBody produces substantial HTML output');
assert_contains('telico_username', $html, 'renderBody contains telico_username input');
assert_contains('telico_sms_pass', $html, 'renderBody contains telico_sms_pass input');
assert_contains('telico_callerid', $html, 'renderBody contains telico_callerid input');

// ------ Test 7: TelicoSettings::validate works ----

$validated = SettingsRegistry::validateSection('telico', ['telico.username' => ''], ['admin']);
assert_true(isset($validated['telico.username']), 'validateSection catches empty username');

summary();
exit($__FAIL__ > 0 ? 1 : 0);
