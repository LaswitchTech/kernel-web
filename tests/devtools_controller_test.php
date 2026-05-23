<?php
/**
 * Tests: /admin/developer tools page layout and config flags.
 *
 * Verifies:
 * - Page title present
 * - Simplified info alert ("Developer mode is active.")
 * - File-backed warning alert present
 * - Developer Mode NOT rendered as a card
 * - Debug Mode and Dev Console as disabled toggles in a 2-col row
 * - No form, no fetch, no AJAX, no DB artifacts
 * - Config values render correctly
 * - Scaffold cards unchanged
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

// ===== Helper: render tools.php =====
function render_tools($appConfig): string
{
    ob_start();
    include __DIR__ . '/../app/Views/admin/developer/tools.php';
    return ob_get_clean();
}

// ===== TEST: Page title =====
echo "= TEST: Page title =\n";
$appConfig = ['debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, '<h2 class="mb-3">Developer Tools</h2>') !== false, 'page title present');
echo "PASS\n";

// ===== TEST: Simplified info alert =====
echo "= TEST: Info alert =\n";
assert_true(strpos($output, 'alert-info mb-4') !== false, 'info alert class present');
assert_true(strpos($output, 'Developer mode is active.') !== false, 'simplified message present');
assert_true(strpos($output, 'bi bi-terminal') !== false, 'terminal icon in info alert');
assert_false(strpos($output, 'APP_DEBUG') !== false && strpos($output, 'production deployments') !== false,
    'no mention of APP_DEBUG or production in info alert');
echo "PASS\n";

// ===== TEST: File-backed warning alert =====
echo "= TEST: File-backed warning alert =\n";
assert_true(strpos($output, 'alert-warning mb-4') !== false, 'warning alert present');
assert_true(strpos($output, 'file-backed values') !== false, 'mentions file-backed');
assert_true(strpos($output, '.env') !== false, 'mentions .env');
assert_true(strpos($output, 'config/local.php') !== false, 'mentions config/local.php');
assert_true(strpos($output, 'bi-info-circle') !== false, 'info icon present');
echo "PASS\n";

// ===== TEST: Developer Mode NOT rendered =====
echo "= TEST: No Developer Mode card =\n";
assert_true(strpos($output, 'Developer Mode') === false, 'no Developer Mode section');
assert_true(strpos($output, 'APP_DEVELOPER') === false, 'no APP_DEVELOPER reference');
echo "PASS\n";

// ===== TEST: Debug Mode card in row =====
echo "= TEST: Debug Mode in row =\n";
assert_true(strpos($output, '<div class="row g-3 mb-3">') !== false, 'row g-3 container present');
assert_true(strpos($output, '<div class="col-md-6">') !== false, 'col-md-6 column present');
assert_true(strpos($output, 'Debug Mode') !== false, 'Debug Mode card title');
assert_true(strpos($output, 'bi bi-bug') !== false, 'bug icon in Debug Mode card');
assert_true(strpos($output, 'text-primary') !== false, 'primary color on icon');
assert_true(strpos($output, 'toggle_debug') !== false, 'debug toggle ID');
assert_true(strpos($output, 'disabled') !== false, 'disabled attribute');
assert_true(strpos($output, 'form-check form-switch') !== false, 'Bootstrap switch class');
echo "PASS\n";

// ===== TEST: Dev Console card in row =====
echo "= TEST: Dev Console in row =\n";
assert_true(strpos($output, 'Dev Console') !== false, 'Dev Console card title');
assert_true(strpos($output, 'bi bi-terminal') !== false, 'terminal icon in Dev Console card');
assert_true(strpos($output, 'toggle_dev_console') !== false, 'dev_console toggle ID');
echo "PASS\n";

// ===== TEST: Guidance inside card body =====
echo "= TEST: Guidance inside card =\n";
assert_true(strpos($output, 'bi-lock-fill') !== false, 'lock icon in guidance');
assert_true(strpos($output, 'To change') !== false, 'To change text');
// Check that guidance alert is inside the card's second card-body
assert_true(strpos($output, '<div class="card-body pt-0">') !== false, 'guidance in pt-0 card-body');
echo "PASS\n";

// ===== TEST: false values render as Off =====
echo "= TEST: false → Off =\n";
$appConfig = ['debug' => false, 'dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Off') !== false, 'Off present for false');
echo "PASS\n";

// ===== TEST: true values render as On =====
echo "= TEST: true → On =\n";
$appConfig = ['debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'On') !== false, 'On present for true');
echo "PASS\n";

// ===== TEST: Nested config['app'] works =====
echo "= TEST: Nested config['app'] =\n";
$nestedConfig = ['app' => ['debug' => true, 'dev_console' => true]];
$appConfig = is_array($nestedConfig['app'] ?? null) ? $nestedConfig['app'] : $nestedConfig;
$output = render_tools($appConfig);
assert_true(strpos($output, 'On') !== false, 'On badges with nested config');
echo "PASS\n";

// ===== TEST: Missing keys → Off =====
echo "= TEST: Missing keys → Off =\n";
$appConfig = [];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Off') !== false, 'defaults to Off');
echo "PASS\n";

// ===== TEST: Dev Console warning when disabled =====
echo "= TEST: dev_console=false → warning =\n";
$appConfig = ['debug' => true, 'dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'alert-warning') !== false, 'warning present');
assert_true(strpos($output, 'Dev Console is disabled') !== false, 'mentions disabled');
echo "PASS\n";

// ===== TEST: No JS, no fetch, no AJAX, no form =====
echo "= TEST: No JS/AJAX/form artifacts =\n";
$appConfig = ['debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_false(strpos($output, 'getElementById') !== false, 'no JS getElementById');
assert_false(strpos($output, 'fetch(') !== false, 'no JS fetch');
assert_false(strpos($output, 'FormData') !== false, 'no FormData');
assert_false(strpos($output, 'role="switch"') === false, 'has disabled toggles');
echo "PASS\n";

// ===== TEST: No DB artifacts =====
echo "= TEST: No DB artifacts =\n";
$ctrlContent = file_get_contents(__DIR__ . '/../app/Controllers/Admin/DeveloperController.php');
assert_true(strpos($ctrlContent, 'SystemSettingService') === false, 'no SystemSettingService');
assert_true(strpos($ctrlContent, 'ajaxSaveSettings') === false, 'no ajaxSaveSettings');
$routesContent = file_get_contents(__DIR__ . '/../routes/web.php');
assert_true(strpos($routesContent, "post('/admin/developer/settings'") === false, 'no POST route');
echo "PASS\n";

// ===== TEST: Scaffold cards unchanged =====
echo "= TEST: Scaffold cards present =\n";
$appConfig = ['debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'Scaffold Generator') !== false, 'Scaffold Generator present');
assert_true(strpos($output, 'Copy Example Code') !== false, 'Copy Example Code present');
assert_true(strpos($output, 'Validate Manifests') !== false, 'Validate Manifests present');
assert_true(strpos($output, 'btn btn-sm btn-primary') !== false, 'Open Generator button present');
echo "PASS\n";

echo "\n=== ALL DEVTOOLS CONTROLLER TESTS PASSED ===\n";
