<?php

/**
 * SMTP settings visibility regression test.
 *
 * Proves:
 *  - SMTP plugin manifest loads without throwing PluginException
 *  - requires.kernel constraint is valid (semver)
 *  - plugin_hooks parsed correctly (plugins.bootstrap → SmtpHooks::bootstrap)
 *  - SmtpHooks class loads and is callable
 *  - bootstrap() executes without error, adds section to SettingsRegistry
 *  - Section is visible for admin user (permission => null)
 *  - Section renderBody produces expected fields
 *
 * Run: php tests/smtp_settings_test.php
 * Assertions: 18
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Container;
use App\Core\Plugins\PluginLoader;
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
$cls = '\App\Services\Extensions\ExtensionDependencyResolver';
assert_true($cls::isValidConstraint($minKernel), "requires.kernel '$minKernel' is valid via isValidConstraint");

// ------ Test 3: plugin_hooks parsed correctly ----

$hooks = $manifest->pluginHooks();
assert_true(count($hooks) >= 1, 'At least one plugin_hook parsed');
assert_true(isset($hooks['plugins.bootstrap']), 'plugins.bootstrap hook present');
$bootstrap = $hooks['plugins.bootstrap'];
assert_not_null($bootstrap['callback'], 'plugins.bootstrap has callback');
assert_equal('Plugins\\Smtp\\SmtpHooks::bootstrap', $bootstrap['callback'], 'Bootstrap callback is correct');

// ------ Test 4: SmtpHooks class loads and is callable via autoloader ----

// Test bootstrap must work with the autoloader (not just require_once).
// Register a Plugins\ autoloader to simulate production boot.
spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'Plugins\\', strlen('Plugins\\')) !== 0) {
        return;
    }
    $relative = substr($class, strlen('Plugins\\'));
    $parts = explode('\\', $relative);
    if (count($parts) < 2) {
        return;
    }
    $pluginName = strtolower($parts[0]);
    $classPath = implode('/', array_slice($parts, 1));
    $srcDir = __DIR__ . '/../lib/plugins/' . $pluginName . '/src';
    $file = $srcDir . '/' . $classPath . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

$ref = new ReflectionClass('Plugins\Smtp\SmtpHooks');
assert_true($ref->hasMethod('bootstrap'), 'SmtpHooks has bootstrap method');
$method = $ref->getMethod('bootstrap');
assert_true($method->isPublic(), 'bootstrap is public');
assert_true(is_callable(['Plugins\Smtp\SmtpHooks', 'bootstrap']), 'bootstrap is is_callable');

// ------ Test 5: Full bootstrap() path registers section via PluginLoader ----

SettingsRegistry::clear();

$container = new Container();
$pluginsDir = realpath(__DIR__ . '/../lib/plugins');
assert_true($pluginsDir !== false, 'plugins directory exists');
$loader = new PluginLoader($pluginsDir, $container);
$loader->load();
$loader->getRegistry()->setContainer($container);

// Execute bootstrap hooks (this calls SmtpHooks::bootstrap() which calls
// registerSettings() and swapTransport()).
$loader->executePluginHooks('plugins.bootstrap', ['kernelRoot' => __DIR__]);

assert_true(SettingsRegistry::hasSections(), 'After bootstrap(), SettingsRegistry has sections');

$sections = SettingsRegistry::getSections(['admin']);
assert_true(count($sections) >= 1, 'At least one section visible for admin after bootstrap');

$smtpSection = SettingsRegistry::getSection('smtp');
assert_not_null($smtpSection, 'SMTP section registered in registry via bootstrap');
assert_equal('smtp', $smtpSection->id, 'Section ID is smtp');
assert_equal('SMTP Settings', $smtpSection->label, 'Section label is SMTP Settings');
assert_equal('right', $smtpSection->column, 'Section column is right');
assert_true($smtpSection->isVisible(['admin']), 'Section visible for admin permission');
assert_true($smtpSection->isVisible([]), 'Section visible for any permission (permission=null)');

// ------ Test 6: Section keys are valid (smtp. prefix) ----

$keys = SettingsRegistry::getSectionKeys('smtp');
assert_true(count($keys) >= 1, 'Section has at least one key');
foreach ($keys as $key) {
    assert_true(str_starts_with($key, 'smtp.'), "Key '$key' has smtp. prefix");
}

// ------ Test 7: renderBody produces expected HTML content ----

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

// ------ Test 8: validateSection works ----

$validated = SettingsRegistry::validateSection('smtp', ['smtp.host' => 'not-valid-host!!', 'smtp.port' => '99999'], ['admin']);
assert_true(count($validated) >= 2, 'validateSection catches host and port errors');
assert_true(isset($validated['smtp.host']), 'validateSection returns smtp.host error');
assert_true(isset($validated['smtp.port']), 'validateSection returns smtp.port error');

summary();
exit($__FAIL__ > 0 ? 1 : 0);
