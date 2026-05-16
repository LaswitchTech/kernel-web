<?php

/**
 * SMTP settings visibility regression test.
 *
 * Proves:
 *  - SMTP plugin manifest loads without throwing PluginException
 *  - requires.kernel constraint is valid (semver)
 *  - plugin_hooks parsed correctly (plugins.bootstrap → SmtpHooks::bootstrap)
 *  - SmtpHooks class loads and is callable
 *  - SmtpSettings::register() adds section to SettingsRegistry
 *  - Section is visible for admin user
 *  - Section renderBody produces expected fields
 *
 * Run: php tests/smtp_settings_test.php
 * Assertions: 15
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Plugins\PluginManifest;
use App\Core\SettingsRegistry;
use App\Core\SettingsSection;

// ------ Test 1: Manifest loads without throwing PluginException ----

$pluginJson = json_decode(file_get_contents(__DIR__ . '/../lib/plugins/smtp/plugin.json'), true);
assert_not_null($pluginJson, 'SMTP plugin.json decodes to array');
$manifest = new PluginManifest($pluginJson);
assert_not_null($manifest, 'PluginManifest constructs');
assert_equal('smtp', $manifest->name(), 'Manifest name is smtp');
assert_true($manifest->enabled(), 'Manifest enabled is true');

// ------ Test 2: requires.kernel is a valid constraint ----

$minKernel = $manifest->minKernelVersion();
assert_true($minKernel !== '', 'requires.kernel parsed (non-empty)');

// Load the dependency resolver class (it's in app/Services/Extensions/)
require_once __DIR__ . '/../app/Services/Extensions/ExtensionDependencyResolver.php';
$cls = '\App\Services\Extensions\ExtensionDependencyResolver';
assert_true($cls::isValidConstraint($minKernel), "requires.kernel '$minKernel' is valid via isValidConstraint");

// ------ Test 3: plugin_hooks parsed correctly ----

$hooks = $manifest->pluginHooks();
assert_true(count($hooks) >= 1, 'At least one plugin_hook parsed');
assert_true(isset($hooks['plugins.bootstrap']), 'plugins.bootstrap hook present');
$bootstrap = $hooks['plugins.bootstrap'];
assert_not_null($bootstrap['callback'], 'plugins.bootstrap has callback');
assert_equal('Plugins\\Smtp\\SmtpHooks::bootstrap', $bootstrap['callback'], 'Bootstrap callback is correct');

// ------ Test 4: SmtpHooks class loads and is callable ----

require_once __DIR__ . '/../lib/plugins/smtp/src/SmtpHooks.php';
$ref = new ReflectionClass('Plugins\Smtp\SmtpHooks');
assert_true($ref->hasMethod('bootstrap'), 'SmtpHooks has bootstrap method');
$method = $ref->getMethod('bootstrap');
assert_true($method->isPublic(), 'bootstrap is public');
assert_true($method->isStatic(), 'bootstrap is static');
assert_true(is_callable(['Plugins\Smtp\SmtpHooks', 'bootstrap']), 'bootstrap is is_callable');

// ------ Test 5: SmtpSettings class loads and has register ----

require_once __DIR__ . '/../lib/plugins/smtp/src/SmtpSettings.php';
$ref = new ReflectionClass('Plugins\Smtp\SmtpSettings');
$ref = new ReflectionClass('Plugins\Smtp\SmtpSettings');
assert_true($ref->hasMethod('register'), 'SmtpSettings has register method');

// ------ Test 6: register() adds section to SettingsRegistry ----

SettingsRegistry::clear();
Plugins\Smtp\SmtpSettings::register();
assert_true(SettingsRegistry::hasSections(), 'After register(), SettingsRegistry has sections');

$sections = SettingsRegistry::getSections(['admin']);
assert_true(count($sections) >= 1, 'At least one section visible for admin');

$smtpSection = SettingsRegistry::getSection('smtp');
assert_not_null($smtpSection, 'SMTP section registered in registry');
assert_equal('smtp', $smtpSection->id, 'Section ID is smtp');
assert_equal('SMTP Settings', $smtpSection->label, 'Section label is SMTP Settings');
assert_equal('right', $smtpSection->column, 'Section column is right');
assert_true($smtpSection->isVisible(['admin']), 'Section visible for admin permission');
assert_true($smtpSection->isVisible([]), 'Section visible for any permission (permission=null)');

// ------ Test 7: Section keys are valid (smtp. prefix) ----

$keys = SettingsRegistry::getSectionKeys('smtp');
assert_true(count($keys) >= 1, 'Section has at least one key');
foreach ($keys as $key) {
    assert_true(str_starts_with($key, 'smtp.'), "Key '$key' has smtp. prefix");
}

// ------ Test 8: renderBody produces expected HTML content ----

$ctx = ['settings' => ['smtp.host' => 'localhost', 'smtp.port' => '587', 'smtp.encryption' => 'tls'], 'errors' => []];
$html = $smtpSection->renderBody($ctx);
assert_true(strlen($html) > 100, 'renderBody produces substantial HTML output');
assert_contains('smtp_host', $html, 'renderBody contains smtp_host input');
assert_contains('smtp_port', $html, 'renderBody contains smtp_port input');
assert_contains('smtp_encryption', $html, 'renderBody contains smtp_encryption select');
assert_contains('smtp_user', $html, 'renderBody contains smtp_user input');
assert_contains('smtp_pass', $html, 'renderBody contains smtp_pass input');
assert_contains('smtp_from_address', $html, 'renderBody contains smtp_from_address input');
assert_contains('smtp_from_name', $html, 'renderBody contains smtp_from_name input');

// ------ Test 9: validateSection works ----

$validated = SettingsRegistry::validateSection('smtp', ['smtp.host' => 'not-valid-host!!', 'smtp.port' => '99999'], ['admin']);
assert_true(count($validated) >= 2, 'validateSection catches host and port errors');
assert_true(isset($validated['smtp.host']), 'validateSection returns smtp.host error');
assert_true(isset($validated['smtp.port']), 'validateSection returns smtp.port error');

summary();
exit($__FAIL__ > 0 ? 1 : 0);
