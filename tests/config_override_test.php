<?php

/**
 * Tests for ConfigOverrideService — file-backed config override writer.
 *
 * Tests: dot-key write, update existing, preserve unrelated keys,
 * boolean normalization, invalid key rejection, generated PHP is valid.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Services\ConfigOverrideService;
use App\Core\Config;

// --- Helpers ---

/** @var string */
$__TEMP_DIR__ = '';

function testDir(): string
{
    global $__TEMP_DIR__;
    if ($__TEMP_DIR__ === '') {
        $__TEMP_DIR__ = sys_get_temp_dir() . '/config_override_test_' . getmypid();
        mkdir($__TEMP_DIR__, 0700, true);
    }
    return $__TEMP_DIR__;
}

function tempFile(string $name = 'local.php'): string
{
    return testDir() . '/' . $name;
}

function cleanup(): void
{
    global $__TEMP_DIR__;
    if ($__TEMP_DIR__ !== '' && is_dir($__TEMP_DIR__)) {
        array_map('unlink', glob($__TEMP_DIR__ . '/*'));
        rmdir($__TEMP_DIR__);
        $__TEMP_DIR__ = '';
    }
}

// ============= 1. WRITE A NEW NESTED KEY ===-----==

$local1 = tempFile('test1.php');
$svc1 = new ConfigOverrideService($local1);

assert_true(empty($svc1->readLocal()), 'empty local file returns []');
assert_true($svc1->set('app.name', 'TestApp'), 'set app.name succeeds');
$localData1 = $svc1->readLocal();
assert_equal('TestApp', $localData1['app']['name'], 'app.name stored correctly');

unlink($local1);

// ============= 2. UPDATE AN EXISTING NESTED KEY ==--

$local2 = tempFile('test2.php');
$svc2 = new ConfigOverrideService($local2);

// Use setBatch to write multiple keys atomically (each set() reads from file)
$svc2->setBatch([
    'app.name' => 'Updated',
    'auth.two_factor.enforced' => true,
]);
$localData2 = $svc2->readLocal();
assert_equal('Updated', $localData2['app']['name'], 'app.name updated');

// Unrelated nested keys are preserved
assert_true($localData2['auth']['two_factor']['enforced'], 'auth.two_factor.enforced set');

unlink($local2);

// ============= 3. PRESERVE UNRELATED LOCAL.PHP VALUES ==---

$local3 = tempFile('test3.php');
$svc3 = new ConfigOverrideService($local3);

// Simulate existing local.php with database config
file_put_contents($local3, "<?php\nreturn ['database' => ['driver' => 'sqlite'],];\n");
$svc3->set('app.name', 'PreserveTest');
$localData3 = $svc3->readLocal();
assert_equal('PreserveTest', $localData3['app']['name'], 'new key set');
assert_equal('sqlite', $localData3['database']['driver'], 'unrelated database key preserved');

unlink($local3);

// ============= 4. BOOLEAN CONVERSION FROM CHECKBOX VALUES ==

$local4 = tempFile('test4.php');
$svc4 = new ConfigOverrideService($local4);

// Checkbox sends '1'
assert_true(ConfigOverrideService::normalizeFormValue('1'), "'1' normalizes to true");
assert_true(ConfigOverrideService::normalizeFormValue(true), 'true normalizes to true');
assert_true(ConfigOverrideService::normalizeFormValue(1), 'int 1 normalizes to 1');

// Checkbox sends '0'
assert_false(ConfigOverrideService::normalizeFormValue('0'), "'0' normalizes to false");
assert_false(ConfigOverrideService::normalizeFormValue(false), 'false normalizes to false');

// Strings are preserved
assert_equal('hello', ConfigOverrideService::normalizeFormValue('hello'), 'string preserved');
assert_equal('false', ConfigOverrideService::normalizeFormValue('false'), "string 'false' preserved (not boolean)");
assert_equal('', ConfigOverrideService::normalizeFormValue(''), 'empty string preserved');

// Set booleans and verify they survive write/read
assert_true($svc4->set('app.debug', true), 'set bool true');
$boolData = $svc4->readLocal();
assert_true($boolData['app']['debug'], 'bool true stored and read back');

assert_true($svc4->set('app.debug', false), 'set bool false');
$boolData2 = $svc4->readLocal();
assert_false($boolData2['app']['debug'], 'bool false stored and read back');

unlink($local4);

// ============= 5. INVALID KEY REJECTION ===---==

// Test via set() for single-key validation
$local5a = tempFile('test5a.php');
$svc5a = new ConfigOverrideService($local5a);

assert_false($svc5a->set('', false), 'empty key rejected');
assert_false($svc5a->set('top', false), 'single-segment key rejected');
assert_false($svc5a->set('a.b.c.', false), 'trailing empty segment rejected');
if (file_exists($local5a)) unlink($local5a);

// Batch: all valid → all written
$local5b = tempFile('test5b.php');
$svc5b = new ConfigOverrideService($local5b);
$svc5b->setBatch(['app.name' => 'Valid', 'a.b' => 'also_valid']);
$batchLocal = $svc5b->readLocal();
assert_equal('Valid', $batchLocal['app']['name'], 'valid key in batch stored');
assert_equal('also_valid', $batchLocal['a']['b'], 'valid key a.b stored');
unlink($local5b);

// Batch: mix valid+invalid → none written (atomic)
$local5c = tempFile('test5c.php');
$svc5c = new ConfigOverrideService($local5c);
$batchErrors = $svc5c->setBatch([
    'app.name' => 'Valid',
    'badkey'   => true,
]);
assert_equal(1, count($batchErrors), 'batch rejects invalid keys');
assert_false(file_exists($local5c), 'invalid key → no file written');
if (file_exists($local5c)) unlink($local5c);

// ============= 6. GENERATED PHP FILE IS VALID AND SAFE ===---==

$local6 = tempFile('test6.php');
$svc6 = new ConfigOverrideService($local6);

// Use setBatch (atomic) for mixed types
$svc6->setBatch([
    'app.name' => 'TestApp',
    'app.version' => '1.0.0',
    'app.debug' => true,
    'auth.two_factor.enforced' => false,
    'database.driver' => 'sqlite',
    'app.nested.deep.value' => '42',
]);

// Verify file exists and has content
assert_true(file_exists($local6), 'generated file exists');
$code = file_get_contents($local6);
assert_true(strlen($code) > 0, 'generated file has content');

// Verify values via require (safe — temp file in our dir)
$compiled = @require $local6;
assert_true(is_array($compiled), 'generated code compiles to array via require');

// Verify values
assert_equal('TestApp', $compiled['app']['name'], 'app.name');
assert_equal('1.0.0', $compiled['app']['version'], 'app.version');
assert_true($compiled['app']['debug'] === true, 'app.debug is true');
assert_equal('42', $compiled['app']['nested']['deep']['value'], 'deep value');

unlink($local6);

// ============= 7. STRING ESCAPING IN GENERATED PHP ==--

$local7 = tempFile('test7.php');
$svc7 = new ConfigOverrideService($local7);

// Values with quotes and backslashes
$svc7->set('app.name', "Test's \"App\"");
$svc7->set('app.url', 'https://example.com\\path');
assert_true(file_exists($local7), 'test7 file exists');

// Should compile to array
$compiled7 = @require $local7;
assert_true(is_array($compiled7), 'escaped strings compile to array');

unlink($local7);

// ============= 8. WRITE TO NON-EXISTENT PATH FAILS GRACEFULLY ==

$svc8 = new ConfigOverrideService('/nonexistent/path/config/local.php');
assert_false($svc8->set('app.name', 'Fail'), 'write to nonexistent dir fails');

// ============= 9. MULTI-LEVEL DEEP PATH CREATES NESTED ARRAYS ==

$local9 = tempFile('test9.php');
$svc9 = new ConfigOverrideService($local9);

assert_true($svc9->set('a.b.c.d', 'deep'), 'deep nested key set');
$deepData = $svc9->readLocal();
assert_equal('deep', $deepData['a']['b']['c']['d'], 'deep nesting resolved');

unlink($local9);

// ============= 10. OVERWRITE FLAT VALUE WITH NESTED ===----

$local10 = tempFile('test10.php');
$svc10 = new ConfigOverrideService($local10);

// Start with a scalar (can happen if local.php was hand-edited)
file_put_contents($local10, "<?php\nreturn ['app' => 'old_flat_value',];\n");

// Overwrite the scalar with a nested structure
assert_true($svc10->set('app.name', 'OverrideFlat'), 'overwrite flat with nested');
$overData = $svc10->readLocal();
assert_equal('OverrideFlat', $overData['app']['name'], 'nested key exists after overwrite');
assert_true(!is_string($overData['app']), 'app is no longer a string');

unlink($local10);

// Cleanup
cleanup();

// ============= SUMMARY ==

summary();
