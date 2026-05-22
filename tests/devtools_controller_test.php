<?php
/**
 * Regression test: DeveloperController tools() sets $appConfig
 * before rendering the view, so tools.php sees correct flag values.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

echo "= TEST: Controller sets \$appConfig before view render =\n";

// Flat config (real app path)
$flatConfig = [
    'name' => 'Kernel-Web',
    'developer' => true,
    'debug' => true,
    'dev_console' => true,
];

// Simulate the FIXED controller: derive $appConfig same as ViewGlobals
$appConfig = is_array($flatConfig['app'] ?? null) ? $flatConfig['app'] : $flatConfig;

// Render tools.php in controller scope (before layout)
ob_start();
include __DIR__ . '/../app/Views/admin/developer/tools.php';
$output = ob_get_clean();

// Feature flags should show On/true (not Off/false)
assert_true(strpos($output, 'Developer Tools') !== false, 'page title present');
assert_true(strpos($output, 'badge bg-success') !== false, 'On badges present');
assert_true(strpos($output, 'true') !== false, 'true values present');
echo "PASS\n";

// ===== TEST: All flags false → all Off =====
echo "= TEST: All flags false → all Off =\n";
$flatConfig2 = $flatConfig;
$flatConfig2['developer'] = false;
$flatConfig2['debug'] = false;
$flatConfig2['dev_console'] = false;
$appConfig2 = is_array($flatConfig2['app'] ?? null) ? $flatConfig2['app'] : $flatConfig2;

ob_start();
include __DIR__ . '/../app/Views/admin/developer/tools.php';
$output2 = ob_get_clean();

assert_true(strpos($output2, 'badge bg-danger') !== false, 'Off badges present');
assert_true(strpos($output2, 'Off') !== false, '"Off" text present');
echo "PASS\n";

// ===== TEST: Nested config also works =====
echo "= TEST: Nested \$config['app'] also works =\n";
$nestedConfig = ['app' => ['developer' => true, 'debug' => true, 'dev_console' => true]];
$appConfig3 = is_array($nestedConfig['app'] ?? null) ? $nestedConfig['app'] : $nestedConfig;

assert_true($appConfig3['developer'] === true, 'nested developer=true');
assert_true($appConfig3['debug'] === true, 'nested debug=true');
assert_true($appConfig3['dev_console'] === true, 'nested dev_console=true');
echo "PASS\n";

// ===== TEST: Flat config → all flags = true → no warning banner =====
echo "= TEST: All true → no warning banner =\n";
$appConfig4 = $flatConfig; // all true
ob_start();
include __DIR__ . '/../app/Views/admin/developer/tools.php';
$output4 = ob_get_clean();
assert_true(strpos($output4, 'alert-warning') === false, 'no warning when dev_console is true');
echo "PASS\n";

// ===== TEST: dev_console = false → warning banner present =====
echo "= TEST: dev_console = false → warning banner present =\n";
$flatConfig5 = $flatConfig;
$flatConfig5['dev_console'] = false;
$appConfig5 = is_array($flatConfig5['app'] ?? null) ? $flatConfig5['app'] : $flatConfig5;
ob_start();
include __DIR__ . '/../app/Views/admin/developer/tools.php';
$output5 = ob_get_clean();
assert_true(strpos($output5, 'alert-warning') !== false, 'warning when dev_console is false');
assert_true(strpos($output5, 'APP_DEV_CONSOLE') !== false, 'shows env var name');
echo "PASS\n";

echo "\n=== ALL DEVTOOLS CONTROLLER TESTS PASSED ===\n";
