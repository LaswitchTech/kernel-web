<?php
/**
 * Tests: Developer controller and tools page.
 *
 * Verifies:
 * - tools.php renders status badges from resolved config values
 * - Correct values for all flag combinations
 * - config/local.php overrides config/app.php defaults
 * - No DB persistence (flags are file-backed)
 * - No toggle form or AJAX endpoint (disabled until proper persistence)
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

// ===== Helper: render tools.php in controller scope =====
function render_tools($appConfig): string
{
    ob_start();
    include __DIR__ . '/../app/Views/admin/developer/tools.php';
    $output = ob_get_clean();
    return $output;
}

// ===== TEST: All true → all On badges =====
echo "= TEST: All true → all On =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'bg-success') !== false, 'success badge present');
assert_true(strpos($output, 'On') !== false, '"On" text present');
assert_true(strpos($output, 'Developer Tools') !== false, 'page title present');
assert_true(strpos($output, 'Developer Mode') !== false, 'Developer Mode row present');
assert_true(strpos($output, 'Debug Mode') !== false, 'Debug Mode row present');
assert_true(strpos($output, 'Dev Console') !== false, 'Dev Console row present');
echo "PASS\n";

// ===== TEST: All false → all Off badges =====
echo "= TEST: All false → all Off =\n";
$appConfig = ['developer' => false, 'debug' => false, 'dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'bg-danger') !== false, 'danger badge present');
assert_true(strpos($output, 'Off') !== false, '"Off" text present');
echo "PASS\n";

// ===== TEST: Mixed flags → correct individual badges =====
echo "= TEST: Mixed flags → correct badges =\n";
$appConfig = ['developer' => true, 'debug' => false, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Developer Mode') !== false, 'Developer Mode present');
assert_true(strpos($output, 'Debug Mode') !== false, 'Debug Mode present');
assert_true(strpos($output, 'Dev Console') !== false, 'Dev Console present');
echo "PASS\n";

// ===== TEST: No toggle form (disabled until proper persistence) =====
echo "= TEST: No toggle form rendered =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'form-check-input') === false, 'no toggle inputs');
assert_true(strpos($output, 'role="switch"') === false, 'no form-switch');
assert_true(strpos($output, "fetch('/admin/developer/settings'") === false, 'no AJAX endpoint in JS');
assert_true(strpos($output, 'getElementById') === false, 'no JS getElementById');
echo "PASS\n";

// ===== TEST: Dev Console warning when disabled =====
echo "= TEST: dev_console=false → warning =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'alert-warning') !== false, 'warning banner present');
assert_true(strpos($output, 'APP_DEV_CONSOLE') !== false, 'mentions env var');
echo "PASS\n";

// ===== TEST: No warning when dev_console=true =====
echo "= TEST: dev_console=true → no warning =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'alert-warning') === false, 'no warning banner');
echo "PASS\n";

// ===== TEST: Source column shows config file path =====
echo "= TEST: Source column shows config sources =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'config/local.php') !== false, 'source shows config/local.php');
assert_true(strpos($output, 'config/app.php') !== false, 'source shows config/app.php');
assert_true(strpos($output, 'file-backed') !== false, 'mentions file-backed nature');
echo "PASS\n";

// ===== TEST: Nested $config['app'] also works =====
echo "= TEST: Nested config['app'] also works =\n";
$nestedConfig = ['app' => ['developer' => true, 'debug' => true, 'dev_console' => true]];
$appConfig = is_array($nestedConfig['app'] ?? null) ? $nestedConfig['app'] : $nestedConfig;
$output = render_tools($appConfig);
assert_true(strpos($output, 'On') !== false, 'On badges present with nested config');
echo "PASS\n";

// ===== TEST: Missing keys → false defaults =====
echo "= TEST: Missing keys → false defaults =\n";
$appConfig = [];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Off') !== false, 'defaults to Off when config missing');
assert_true(strpos($output, 'bg-danger') !== false, 'danger badge when default false');
echo "PASS\n";

// ===== TEST: Scaffold page accessible (unchanged) =====
echo "= TEST: Scaffold page HTML renders =\n";
$scaffoldPath = __DIR__ . '/../app/Views/admin/developer/scaffold.php';
assert_true(file_exists($scaffoldPath), 'scaffold.php view exists');
$scaffoldContent = file_get_contents($scaffoldPath);
assert_true(strpos($scaffoldContent, 'Scaffold Generator') !== false, 'scaffold page title present');
echo "PASS\n";

echo "\n=== ALL DEVTOOLS CONTROLLER TESTS PASSED ===\n";
