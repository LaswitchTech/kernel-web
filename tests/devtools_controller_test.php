<?php
/**
 * Tests: Developer controller and tools page — config flags as toggles.
 *
 * Verifies:
 * - tools.php renders Developer Mode as read-only status badge
 * - Debug Mode and Dev Console render as disabled toggle switches
 * - Toggles reflect actual resolved config values
 * - No DB persistence, no SystemSettingService usage
 * - No AJAX endpoint, no form submission
 * - config/local.php / .env values render correctly
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

// ===== TEST: Developer Mode shown as read-only badge =====
echo "= TEST: Developer Mode = read-only badge =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Developer Mode') !== false, 'Developer Mode label present');
assert_true(strpos($output, 'bg-success') !== false, 'On badge present');
assert_true(strpos($output, 'true') !== false, 'true value present');
echo "PASS\n";

// ===== TEST: Debug Mode rendered as disabled toggle =====
echo "= TEST: Debug Mode = disabled toggle =\n";
$appConfig = ['debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'toggle_debug') !== false, 'debug toggle ID present');
assert_true(strpos($output, 'role="switch"') !== false, 'form-switch role present');
assert_true(strpos($output, 'disabled') !== false, 'disabled attribute present');
assert_true(strpos($output, 'On') !== false, 'On label present');
echo "PASS\n";

// ===== TEST: Dev Console rendered as disabled toggle =====
echo "= TEST: Dev Console = disabled toggle =\n";
$appConfig = ['dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'toggle_dev_console') !== false, 'dev_console toggle ID present');
assert_true(strpos($output, 'role="switch"') !== false, 'form-switch role present');
assert_true(strpos($output, 'disabled') !== false, 'disabled attribute present');
assert_true(strpos($output, 'On') !== false, 'On label present');
echo "PASS\n";

// ===== TEST: Toggles reflect false values when config=false =====
echo "= TEST: false values render as Off =\n";
$appConfig = ['debug' => false, 'dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Off') !== false, 'Off label present for false values');
// Toggles with false should not have "checked"
$debugPos = strpos($output, 'toggle_debug');
$consolePos = strpos($output, 'toggle_dev_console');
assert_true($debugPos !== false, 'debug toggle present');
assert_true($consolePos !== false, 'dev_console toggle present');
$debugSection = substr($output, $debugPos, 250);
$consoleSection = substr($output, $consolePos, 250);
assert_true(strpos($debugSection, 'checked') === false, 'debug toggle not checked when false');
assert_true(strpos($consoleSection, 'checked') === false, 'dev_console toggle not checked when false');
echo "PASS\n";

// ===== TEST: No form inputs or AJAX (no persistence) =====
echo "= TEST: No persistence mechanism =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'getElementById') === false, 'no JS getElementById');
assert_true(strpos($output, 'fetch(') === false, 'no JS fetch');
assert_true(strpos($output, 'FormData') === false, 'no FormData');
assert_true(strpos($output, 'role="switch"') !== false, 'has disabled toggles instead');
echo "PASS\n";

// ===== TEST: No DB or SystemSettingService in DeveloperController =====
echo "= TEST: No DB usage in DeveloperController =\n";
$ctrlContent = file_get_contents(__DIR__ . '/../app/Controllers/Admin/DeveloperController.php');
assert_true(strpos($ctrlContent, 'SystemSettingService') === false, 'no SystemSettingService');
assert_true(strpos($ctrlContent, 'SystemSettingRepository') === false, 'no SystemSettingRepository');
assert_true(strpos($ctrlContent, 'ajaxSaveSettings') === false, 'no ajaxSaveSettings method');
assert_true(strpos($ctrlContent, 'registerDeveloperSection') === false, 'no registerDeveloperSection');
echo "PASS\n";

// ===== TEST: No POST /admin/developer/settings route =====
echo "= TEST: No POST route for developer settings =\n";
$routesContent = file_get_contents(__DIR__ . '/../routes/web.php');
assert_true(strpos($routesContent, "post('/admin/developer/settings'") === false, 'no POST /admin/developer/settings route');
echo "PASS\n";

// ===== TEST: Config pipeline renders correctly — all true =====
echo "= TEST: All true → all On =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Developer Mode') !== false, 'developer present');
assert_true(strpos($output, 'Debug Mode') !== false, 'debug present');
assert_true(strpos($output, 'Dev Console') !== false, 'dev_console present');
assert_true(strpos($output, 'On') !== false, 'all On');
echo "PASS\n";

// ===== TEST: All false → all Off =====
echo "= TEST: All false → all Off =\n";
$appConfig = ['developer' => false, 'debug' => false, 'dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Off') !== false, 'all Off');
echo "PASS\n";

// ===== TEST: Nested config['app'] also works =====
echo "= TEST: Nested config['app'] =\n";
$nestedConfig = ['app' => ['developer' => true, 'debug' => true, 'dev_console' => true]];
$appConfig = is_array($nestedConfig['app'] ?? null) ? $nestedConfig['app'] : $nestedConfig;
$output = render_tools($appConfig);
assert_true(strpos($output, 'On') !== false, 'On badges present');
echo "PASS\n";

// ===== TEST: Missing keys → false defaults =====
echo "= TEST: Missing keys → Off defaults =\n";
$appConfig = [];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Off') !== false, 'defaults to Off');
echo "PASS\n";

// ===== TEST: Dev Console warning when disabled =====
echo "= TEST: dev_console=false → warning =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'alert-warning') !== false, 'warning banner present');
echo "PASS\n";

// ===== TEST: Dev Console guidance mentions file change steps =====
echo "= TEST: Guidance includes file change steps =\n";
$appConfig = ['dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'APP_DEV_CONSOLE') !== false, 'mentions env var');
assert_true(strpos($output, 'config/local.php') !== false || strpos($output, 'local.php') !== false, 'mentions config/local.php');
echo "PASS\n";

// ===== TEST: Scaffold page still renders =====
echo "= TEST: Scaffold page HTML renders =\n";
$scaffoldPath = __DIR__ . '/../app/Views/admin/developer/scaffold.php';
assert_true(file_exists($scaffoldPath), 'scaffold.php exists');
$scaffoldContent = file_get_contents($scaffoldPath);
assert_true(strpos($scaffoldContent, 'Scaffold Generator') !== false, 'scaffold title present');
echo "PASS\n";

echo "\n=== ALL DEVTOOLS CONTROLLER TESTS PASSED ===\n";
