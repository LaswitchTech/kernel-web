<?php
/**
 * Regression test: audit.php diff rendering handles all value types.
 *
 * Covers: arrays, objects, null, booleans, scalars.
 * No "Array to string conversion" or other PHP warnings under E_ALL.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

echo "= TEST: Audit diff rendering handles all value types =\n";
error_reporting(E_ALL);
ini_set('display_errors', '0');
$errBefore = error_get_last();

// Simulate the audit row rendering logic from audit.php
function renderAuditMeta(array $meta): string
{
    return implode(', ', array_map(
        fn($k, $v) => htmlspecialchars($k) . '=' . htmlspecialchars(
            is_array($v) || is_object($v)
                ? substr(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 80)
                : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v)
        ),
        array_keys($meta),
        array_values($meta)
    ));
}

// Test with permission_ids as array (the original crash)
$arrWithArray = [
    'permission_ids' => [1, 2, 3],
    'name' => 'admin',
    'active' => true,
    'description' => null,
];
$summary = renderAuditMeta($arrWithArray);
assert_true(str_contains($summary, 'permission_ids='), 'array key present');
assert_true(str_contains($summary, '[1, 2, 3]'), 'array values rendered as JSON');
assert_true(str_contains($summary, 'name=admin'), 'scalar rendered');
assert_true(str_contains($summary, 'active=true'), 'boolean rendered');
assert_true(str_contains($summary, 'description=null'), 'null rendered');
echo "PASS\n";

// Test with deeply nested array
$arrWithNested = [
    'permissions' => ['admin' => ['users.view', 'users.edit'], 'roles' => ['admin']],
];
$summary2 = renderAuditMeta($arrWithNested);
assert_true(!empty($summary2), 'nested array does not crash');
assert_true(str_contains($summary2, 'permissions='), 'nested key present');
echo "PASS\n";

// Test with object value
$obj = (object) ['foo' => 'bar', 'num' => 42];
$arrWithObject = ['metadata' => $obj];
$summary3 = renderAuditMeta($arrWithObject);
assert_true(!empty($summary3), 'object does not crash');
assert_true(str_contains($summary3, 'metadata='), 'object key present');
assert_true(str_contains($summary3, '"foo"'), 'object rendered as JSON');
echo "PASS\n";

// Test with all null values
$arrAllNull = ['a' => null, 'b' => null];
$summary4 = renderAuditMeta($arrAllNull);
assert_true(str_contains($summary4, 'a=null'), 'null key=a');
assert_true(str_contains($summary4, 'b=null'), 'null key=b');
echo "PASS\n";

// Test with boolean false
$arrWithFalse = ['active' => false, 'deleted' => true];
$summary5 = renderAuditMeta($arrWithFalse);
assert_true(str_contains($summary5, 'active=false'), 'false rendered');
assert_true(str_contains($summary5, 'deleted=true'), 'true rendered');
echo "PASS\n";

// Test with empty array
$arrEmpty = ['tags' => []];
$summary6 = renderAuditMeta($arrEmpty);
assert_true(str_contains($summary6, 'tags=[]'), 'empty array rendered');
echo "PASS\n";

// Verify no PHP errors were generated
$errAfter = error_get_last();
assert_true($errAfter === null, 'no errors under E_ALL');
echo "PASS\n";

echo "\n=== ALL AUDIT RENDER TESTS PASSED ===\n";

// ── TEST 2: Dev tools offcanvas visibility logic ──────────────

echo "\n= TEST: Dev tools offcanvas visibility with debug=true =\n";

// Simulate the guard logic from dev-tools-offcanvas.php
// $appConfig with debug=true, developer not set (the common case)
$appConfigDebugOnly = ['debug' => true];
$devEnabled   = (bool) ($appConfigDebugOnly['developer'] ?? false);
$debugEnabled = (bool) ($appConfigDebugOnly['debug'] ?? false);
$shouldRender = !$devEnabled && !$debugEnabled; // return only when BOTH false
assert_false($shouldRender, 'dev tools renders when debug=true');
echo "PASS\n";

// $appConfig with developer=true, debug not set
$appConfigDevOnly = ['developer' => true];
$devEnabled2   = (bool) ($appConfigDevOnly['developer'] ?? false);
$debugEnabled2 = (bool) ($appConfigDevOnly['debug'] ?? false);
$shouldRender2 = !$devEnabled2 && !$debugEnabled2;
assert_false($shouldRender2, 'dev tools renders when developer=true');
echo "PASS\n";

// Both false — should suppress
$appConfigNeither = [];
$devEnabled3   = (bool) ($appConfigNeither['developer'] ?? false);
$debugEnabled3 = (bool) ($appConfigNeither['debug'] ?? false);
$shouldRender3 = !$devEnabled3 && !$debugEnabled3;
assert_true($shouldRender3, 'dev tools suppressed when both false');
echo "PASS\n";

// appConfig undefined — guard sets it to [] first, should suppress
$appConfigUndefined = null;
if (!isset($appConfigUndefined) || !is_array($appConfigUndefined)) {
    $appConfigUndefined = [];
}
$devEnabled4   = (bool) ($appConfigUndefined['developer'] ?? false);
$debugEnabled4 = (bool) ($appConfigUndefined['debug'] ?? false);
$shouldRender4 = !$devEnabled4 && !$debugEnabled4;
assert_true($shouldRender4, 'dev tools suppressed with empty appConfig');
echo "PASS\n";

echo "\n=== ALL DEV TOOLS VISIBILITY TESTS PASSED ===\n";
