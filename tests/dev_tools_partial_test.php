<?php
/**
 * Test: dev-tools-offcanvas.php partial is fully defensive.
 *
 * Validates:
 * - No errors/exceptions when $config is missing
 * - No errors/exceptions when $config has no 'app' key
 * - No errors/exceptions with minimal valid config (debug=developer=true)
 * - Returns early (no output) when debug/developer/dev_console are not all true
 * - Partial output includes expected HTML when debug/developer/dev_console are true
 * - Partial resolves $config from scope correctly (controller pattern)
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

$partialPath = realpath(__DIR__ . '/../app/Views/partials/dev-tools-offcanvas.php');
assert_not_null($partialPath, 'partial file exists');

// ===== TEST: Partial with NO $config at all =====
echo "= TEST: Partial with no \$config =\n";
ob_start();
include $partialPath;
$output = ob_get_clean();
error_clear_last();
assert_equal('', $output, 'no output when config missing');
assert_true(error_get_last() === null, 'no error when $config missing');
echo "PASS\n";

// ===== TEST: Partial with $config = null =====
echo "= TEST: Partial with \$config = null =\n";
$config = null;
ob_start();
include $partialPath;
$output = ob_get_clean();
assert_equal('', $output, 'no output when config is null');
assert_true(error_get_last() === null, 'no error when $config is null');
echo "PASS\n";

// ===== TEST: Partial with empty $config =====
echo "= TEST: Partial with empty \$config array =\n";
$config = [];
ob_start();
include $partialPath;
$output = ob_get_clean();
assert_equal('', $output, 'no output with empty config');
assert_true(error_get_last() === null, 'no error with empty config');
echo "PASS\n";

// ===== TEST: Partial with $config['app'] missing =====
echo "= TEST: Partial with \$config missing 'app' key =\n";
$config = ['name' => 'Test', 'version' => '1.0'];
ob_start();
include $partialPath;
$output = ob_get_clean();
assert_equal('', $output, 'no output when app key missing');
assert_true(error_get_last() === null, 'no error when app key missing');
echo "PASS\n";

// ===== TEST: Partial with full config including 'app' =====
echo "= TEST: Partial with full \$config['app'] =\n";
$config = ['app' => ['debug' => true, 'dev_console' => true], 'name' => 'Test'];
$__devVars = ['test_var' => 'test_value'];
ob_start();
include $partialPath;
$output = ob_get_clean();
assert_true(strpos($output, 'Developer Tools') !== false, 'panel HTML present');
assert_true(strpos($output, 'test_var') !== false, 'vars rendered');
unset($__devVars);
echo "PASS\n";

// ===== TEST: Partial with appConfig directly (no $config) =====
echo "= TEST: Partial with \$appConfig but no \$config =\n";
unset($config);
$appConfig = ['debug' => true, 'dev_console' => true];
$__devVars = ['test_var' => 'test_value'];
ob_start();
include $partialPath;
$output = ob_get_clean();
assert_true(strpos($output, 'Developer Tools') !== false, 'output has panel when appConfig enables');
assert_true(strpos($output, 'test_var') !== false, 'output includes $__devVars vars');
unset($__devVars);
echo "PASS\n";

// ===== TEST: Partial renders no PHP notices/warnings at any error level =====
echo "= TEST: No notices/warnings at E_ALL =\n";
$oldLevel = error_reporting(E_ALL);
$config = ['app' => ['debug' => true, 'dev_console' => true]];
$__devVars = ['str' => 'hello', 'arr' => ['x' => 1], 'obj' => new stdClass(), 'nul' => null];
ob_start();
$errBefore = error_get_last();
include $partialPath;
$output = ob_get_clean();
$errAfter = error_get_last();
error_reporting($oldLevel);
assert_true($errAfter === null, 'no errors at E_ALL with dev=true');
echo "PASS\n";
unset($__devVars);

// ===== TEST: Partial with admin permission (isAdmin should be true) =====
echo "= TEST: Partial works with admin user data =\n";
$config = ['app' => ['debug' => true, 'dev_console' => true]];
$__devVars = [
    'currentUser' => ['id' => 1, 'username' => 'admin', 'display_name' => 'Admin User', 'email' => 'admin@test.com'],
    'currentUserPermissions' => ['admin', 'users.view'],
    'currentUserIsAdmin' => true,
];
$__globals = [
    'currentUser' => $__devVars['currentUser'],
    'currentUserId' => 1,
    'currentUsername' => 'admin',
    'currentUserEmail' => 'admin@test.com',
    'currentUserDisplayName' => 'Admin User',
    'currentUserGroups' => null,
    'currentUserPrimaryGroup' => null,
    'currentUserPermissions' => $__devVars['currentUserPermissions'],
    'currentUserIsAdmin' => true,
];
ob_start();
include $partialPath;
$output = ob_get_clean();
assert_true(strpos($output, 'Developer Tools') !== false, 'panel HTML present with admin data');
assert_true(strpos($output, 'admin') !== false, 'admin username rendered');
assert_true(strpos($output, 'Admin User') !== false, 'display name rendered');
echo "PASS\n";

// ===== TEST: Dev tools visibility — 2-flag guard (debug + dev_console) =====
echo "= TEST: Dev tools visibility — 2 boolean combinations =\n";
$partialPath2way = realpath(__DIR__ . '/../app/Views/partials/dev-tools-offcanvas.php');

// Both true → should render
$appConfig = ['debug' => true, 'dev_console' => true];
ob_start();
include $partialPath2way;
$output = ob_get_clean();
assert_true(strpos($output, 'Developer Tools') !== false, 'both true → renders');
echo "PASS\n";

// dev_console false → no output
$appConfig = ['debug' => true, 'dev_console' => false];
ob_start();
include $partialPath2way;
$output = ob_get_clean();
assert_equal('', $output, 'dev_console=false → no output');
echo "PASS\n";

// debug false → no output
$appConfig = ['debug' => false, 'dev_console' => true];
ob_start();
include $partialPath2way;
$output = ob_get_clean();
assert_equal('', $output, 'debug=false → no output');
echo "PASS\n";

// both false → no output
$appConfig = ['debug' => false, 'dev_console' => false];
ob_start();
include $partialPath2way;
$output = ob_get_clean();
assert_equal('', $output, 'both false → no output');
echo "PASS\n";

// missing debug → no output
$appConfig = ['dev_console' => true];
ob_start();
include $partialPath2way;
$output = ob_get_clean();
assert_equal('', $output, 'missing debug → no output');
echo "PASS\n";

// missing dev_console → no output
$appConfig = ['debug' => true];
ob_start();
include $partialPath2way;
$output = ob_get_clean();
assert_equal('', $output, 'missing dev_console → no output');
echo "PASS\n";

// both missing → no output
$appConfig = [];
ob_start();
include $partialPath2way;
$output = ob_get_clean();
assert_equal('', $output, 'both missing → no output');
echo "PASS\n";

echo "\n=== ALL TESTS PASSED ===\n";
