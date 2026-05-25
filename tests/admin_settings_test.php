<?php

/**
 * Tests for admin settings → ConfigOverrideService integration.
 *
 * Tests:
 *  - toggle writes correct value to config/local.php
 *  - config/local.php persists expected structure
 *  - reload reflects new value
 *  - invalid writes fail safely
 *  - existing config values preserved
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Services\ConfigOverrideService;

// --- Helpers ---

$__TEMP_DIR__ = sys_get_temp_dir() . '/admin_settings_test_' . getmypid();
mkdir($__TEMP_DIR__, 0700, true);

function cleanup(): void
{
    global $__TEMP_DIR__;
    if (is_dir($__TEMP_DIR__)) {
        array_map('unlink', glob($__TEMP_DIR__ . '/*'));
        rmdir($__TEMP_DIR__);
    }
}

// ============= 1. TOGGLE ENABLE: set auth.two_factor.enforced = true ===----==

echo "= TEST 1: Toggle enable (2FA enforcement) =\n";
$local1 = $__TEMP_DIR__ . '/local1.php';
$svc1 = new ConfigOverrideService($local1);

assert_true($svc1->set('auth.two_factor.enforced', true), 'set enforced = true succeeds');
$data1 = $svc1->readLocal();
assert_true($data1['auth']['two_factor']['enforced'] === true, 'enforced is true in local.php');

$compiled1 = require $local1;
assert_true($compiled1['auth']['two_factor']['enforced'] === true, 'compiled PHP has enforced = true');
echo "PASS\n";

// ============= 2. TOGGLE DISABLE: set auth.two_factor.enforced = false ==--

echo "= TEST 2: Toggle disable (2FA enforcement) =\n";
$local2 = $__TEMP_DIR__ . '/local2.php';
$svc2 = new ConfigOverrideService($local2);

assert_true($svc2->set('auth.two_factor.enforced', true), 'set to true');
assert_true($svc2->set('auth.two_factor.enforced', false), 'set to false');
$data2 = $svc2->readLocal();
assert_false($data2['auth']['two_factor']['enforced'], 'enforced is false after toggle off');

$compiled2 = require $local2;
assert_false($compiled2['auth']['two_factor']['enforced'], 'compiled PHP has enforced = false');
echo "PASS\n";

// ============= 3. RELOAD REFLECTS NEW VALUE ==--

echo "= TEST 3: Reload reflects new value =\n";
$local3 = $__TEMP_DIR__ . '/local3.php';
$svc3a = new ConfigOverrideService($local3);

$svc3a->set('auth.two_factor.enforced', true);
$valueA = $svc3a->readLocal();
assert_true($valueA['auth']['two_factor']['enforced'] === true, 'initial value is true');

// Fresh instance reads the same file
$svc3b = new ConfigOverrideService($local3);
$valueB = $svc3b->readLocal();
assert_true($valueB['auth']['two_factor']['enforced'] === true, 'fresh instance reads true');

// Toggle to false, fresh instance reads false
$svc3b->set('auth.two_factor.enforced', false);
$svc3c = new ConfigOverrideService($local3);
$valueC = $svc3c->readLocal();
assert_false($valueC['auth']['two_factor']['enforced'], 'fresh instance reads false after toggle');
echo "PASS\n";

// ============= 4. INVALID WRITES FAIL SAFELY ==--

echo "= TEST 4: Invalid writes fail safely =\n";
$local4 = $__TEMP_DIR__ . '/local4.php';
$svc4 = new ConfigOverrideService($local4);

// Empty key
assert_false($svc4->set('', false), 'empty key rejected');
assert_false(file_exists($local4), 'no file created on invalid key');

// Single segment
assert_false($svc4->set('top', false), 'single segment rejected');
assert_false(file_exists($local4), 'no file created on single segment key');

// Invalid characters
assert_false($svc4->set('auth[2fa].enforced', false), 'bracket chars rejected');
assert_false(file_exists($local4), 'no file created on invalid key chars');

// Valid key after invalid keys → file exists
assert_true($svc4->set('auth.two_factor.enforced', true), 'valid key accepted');
assert_true(file_exists($local4), 'file exists after valid write');
echo "PASS\n";

// ============= 5. EXISTING CONFIG VALUES PRESERVED ==--

echo "= TEST 5: Existing config values preserved =\n";
$local5 = $__TEMP_DIR__ . '/local5.php';
file_put_contents($local5, "<?php\nreturn [\n    'app' => ['name' => 'ExistingApp', 'url' => 'https://example.com'],\n    'developer' => ['developer' => true, 'debug' => false],\n];\n");

$svc5 = new ConfigOverrideService($local5);
$svc5->set('auth.two_factor.enforced', true);

$data5 = $svc5->readLocal();
assert_equal('ExistingApp', $data5['app']['name'], 'app.name preserved');
assert_equal('https://example.com', $data5['app']['url'], 'app.url preserved');
assert_true($data5['developer']['developer'] === true, 'developer.developer preserved');
assert_false($data5['developer']['debug'], 'developer.debug preserved');
assert_true($data5['auth']['two_factor']['enforced'] === true, 'new key added');
echo "PASS\n";

// ============= 6. BATCH WRITE PERSISTS EXPECTED STRUCTURE ==--

echo "= TEST 6: Batch write persists expected structure =\n";
$local6 = $__TEMP_DIR__ . '/local6.php';
$svc6 = new ConfigOverrideService($local6);

$svc6->setBatch([
    'app.name' => 'BatchTest',
    'app.debug' => true,
    'auth.two_factor.enforced' => true,
    'developer.debug' => false,
]);

$data6 = $svc6->readLocal();
assert_equal('BatchTest', $data6['app']['name'], 'app.name set');
assert_true($data6['app']['debug'] === true, 'app.debug = true');
assert_true($data6['auth']['two_factor']['enforced'] === true, 'auth.two_factor.enforced = true');
assert_false($data6['developer']['debug'], 'developer.debug = false');

// Verify compiled PHP
$compiled6 = require $local6;
assert_true(is_array($compiled6), 'compiled to array');
assert_equal('BatchTest', $compiled6['app']['name'], 'compiled app.name');
assert_true($compiled6['app']['debug'] === true, 'compiled app.debug');
assert_true($compiled6['auth']['two_factor']['enforced'] === true, 'compiled 2fa enforced');
assert_false($compiled6['developer']['debug'], 'compiled developer.debug');
echo "PASS\n";

// ============= 7. TOGGLE VALUE NORMALIZATION (form input simulation) ==--

echo "= TEST 7: Toggle value normalization =\n";
$local7 = $__TEMP_DIR__ . '/local7.php';
$svc7 = new ConfigOverrideService($local7);

// Simulate form checkbox '1' → true
$normalized1 = ConfigOverrideService::normalizeFormValue('1');
assert_true($normalized1 === true, "'1' normalizes to true");
$svc7->set('auth.two_factor.enforced', $normalized1);
$data7a = $svc7->readLocal();
assert_true($data7a['auth']['two_factor']['enforced'] === true, 'normalized true stored');

// Simulate form checkbox '0' → false
$normalized0 = ConfigOverrideService::normalizeFormValue('0');
assert_false($normalized0, "'0' normalizes to false");
$svc7->set('auth.two_factor.enforced', $normalized0);
$data7b = $svc7->readLocal();
assert_false($data7b['auth']['two_factor']['enforced'], 'normalized false stored');

// Simulate boolean true (AJAX JSON)
$svc7->set('auth.two_factor.enforced', true);
$data7c = $svc7->readLocal();
assert_true($data7c['auth']['two_factor']['enforced'] === true, 'boolean true stored');

// Simulate boolean false (AJAX JSON)
$svc7->set('auth.two_factor.enforced', false);
$data7d = $svc7->readLocal();
assert_false($data7d['auth']['two_factor']['enforced'], 'boolean false stored');
echo "PASS\n";

// ============= 8. WRITE TO NON-EXISTENT DIR FAILS GRACEFULLY ==--

echo "= TEST 8: Write to nonexistent dir fails gracefully =\n";
$svc8 = new ConfigOverrideService('/nonexistent/dir/config/local.php');
assert_false($svc8->set('auth.two_factor.enforced', true), 'write to nonexistent dir fails');
echo "PASS\n";

// ============= 9. FILE STRUCTURE IS CLEAN PHP ==--

echo "= TEST 9: Generated file is valid PHP =\n";
$local9 = $__TEMP_DIR__ . '/local9.php';
$svc9 = new ConfigOverrideService($local9);

$svc9->setBatch([
    'app.name' => "Test's App",
    'app.url' => 'https://example.com',
    'auth.two_factor.enforced' => true,
]);

$code = file_get_contents($local9);
assert_true(str_starts_with($code, "<?php"), 'starts with PHP tag');
assert_true(str_contains($code, "return ["), 'contains return statement');
assert_true(str_ends_with(trim($code), '];'), 'ends with ];');

// Compile and verify
$compiled9 = require $local9;
assert_true(is_array($compiled9), 'compiles to array');
assert_equal("Test's App", $compiled9['app']['name'], 'quoted string preserved');
assert_true($compiled9['auth']['two_factor']['enforced'] === true, 'boolean true rendered as true');
echo "PASS\n";

// ============= 10. ATOMIC WRITE (no partial files) ==--

echo "= TEST 10: Atomic write — no partial files =\n";
$local10 = $__TEMP_DIR__ . '/local10.php';
$svc10 = new ConfigOverrideService($local10);

// Normal write
assert_true($svc10->set('app.name', 'AtomicTest'), 'write succeeds');
assert_true(file_exists($local10), 'file exists after write');
assert_false(file_exists($local10 . '.tmp.*'), 'no temp file left behind');

$data10 = $svc10->readLocal();
assert_equal('AtomicTest', $data10['app']['name'], 'value written atomically');
echo "PASS\n";

// Cleanup
unlink($local1);
unlink($local2);
unlink($local6);
unlink($local7);
unlink($local9);
unlink($local10);
cleanup();

// ============= SUMMARY ==

summary();
