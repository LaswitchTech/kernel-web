<?php

/**
 * Tests for kernel compatibility checking and manifest validation.
 *
 * Tests:
 *   - checkKernelCompatibility: exact/range/^/~ constraints
 *   - checkKernelCompatibility: incompatible constraints
 *   - checkKernelCompatibility: missing/empty constraint = compatible
 *   - checkKernelCompatibility: malformed version = incompatible
 *   - PluginManifest: invalid kernel constraint rejected
 *   - PluginManifest: valid kernel constraint accepted
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Plugins\PluginManifest;
use App\Core\Plugins\PluginException;
use App\Services\Extensions\ExtensionDependencyResolver;

// ===== checkKernelCompatibility: empty/missing constraint =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', ''),
    'Empty constraint is compatible'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', '  '),
    'Whitespace-only constraint is compatible'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', null),
    'Null constraint is compatible'
);

// ===== checkKernelCompatibility: exact match =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', '3.1.0'),
    'Exact match: 3.1.0 == 3.1.0'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('3.0.0', '3.1.0'),
    'Exact mismatch: 3.0.0 != 3.1.0'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('dev', '3.1.0'),
    'dev version with exact constraint is compatible (unknown kernel)'
);

// ===== checkKernelCompatibility: >= constraint =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', '>=3.0.0'),
    '>= constraint: 3.1.0 >= 3.0.0'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.0.0', '>=3.0.0'),
    '>= constraint: 3.0.0 >= 3.0.0 (boundary)'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('2.9.9', '>=3.0.0'),
    '>= constraint: 2.9.9 < 3.0.0'
);

// ===== checkKernelCompatibility: > constraint =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.0.1', '>3.0.0'),
    '> constraint: 3.0.1 > 3.0.0'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('3.0.0', '>3.0.0'),
    '> constraint: 3.0.0 not > 3.0.0 (boundary)'
);

// ===== checkKernelCompatibility: <= constraint =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.0.0', '<=3.0.0'),
    '<= constraint: 3.0.0 <= 3.0.0 (boundary)'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('2.9.9', '<=3.0.0'),
    '<= constraint: 2.9.9 <= 3.0.0'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', '<=3.0.0'),
    '<= constraint: 3.1.0 > 3.0.0'
);

// ===== checkKernelCompatibility: < constraint =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('2.9.9', '<3.0.0'),
    '< constraint: 2.9.9 < 3.0.0'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('3.0.0', '<3.0.0'),
    '< constraint: 3.0.0 not < 3.0.0 (boundary)'
);

// ===== checkKernelCompatibility: ^ (caret) constraint =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', '^3.0.0'),
    '^ constraint: 3.1.0 in ^3.0.0 range'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.9.9', '^3.0.0'),
    '^ constraint: 3.9.9 in ^3.0.0 range'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('2.9.9', '^3.0.0'),
    '^ constraint: 2.9.9 not in ^3.0.0 range'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('4.0.0', '^3.0.0'),
    '^ constraint: 4.0.0 not in ^3.0.0 range (upper bound)'
);

// ^0.x constraints
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('0.2.5', '^0.2.0'),
    '^ constraint: 0.2.5 in ^0.2.0 range'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('0.3.0', '^0.2.0'),
    '^ constraint: 0.3.0 not in ^0.2.0 range'
);

// ===== checkKernelCompatibility: ~ (tilde) constraint =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('2.1.5', '~2.1.0'),
    '~ constraint: 2.1.5 in ~2.1.0 range'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('2.1.0', '~2.1.0'),
    '~ constraint: 2.1.0 in ~2.1.0 range (lower bound)'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('2.2.0', '~2.1.0'),
    '~ constraint: 2.2.0 not in ~2.1.0 range (upper bound)'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('2.0.9', '~2.1.0'),
    '~ constraint: 2.0.9 not in ~2.1.0 range (below lower bound)'
);

// ===== checkKernelCompatibility: compound (AND) constraints =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', '>=2.0.0 <4.0.0'),
    'Compound AND: 3.1.0 satisfies >=2.0.0 <4.0.0'
);
// 4.0.0 does NOT satisfy <4.0.0
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('4.0.0', '>=2.0.0 <4.0.0'),
    'Compound AND: 4.0.0 does not satisfy <4.0.0'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('1.5.0', '>=2.0.0 <4.0.0'),
    'Compound AND: 1.5.0 does not satisfy >=2.0.0'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('2.0.0', '>=2.0.0 <4.0.0'),
    'Compound AND: 2.0.0 satisfies both boundaries'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.9.9', '>=2.0.0 <4.0.0'),
    'Compound AND: 3.9.9 satisfies both boundaries'
);

// ===== checkKernelCompatibility: malformed version =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('dev', '>=2.0.0'),
    'Malformed kernel version "dev" is compatible with >= constraint (unknown kernel)'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1', '>=2.0.0'),
    'Malformed kernel version "3.1" is compatible (unknown kernel)'
);
assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('abc', '>=2.0.0'),
    'Malformed kernel version "abc" is compatible (unknown kernel)'
);

// ===== checkKernelCompatibility: malformed constraint =====

assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', 'abc'),
    'Malformed constraint "abc" is incompatible'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', '>=abc'),
    'Malformed constraint version ">=abc" is incompatible'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('3.1.0', '^'),
    'Malformed constraint "^" is incompatible'
);

// ===== checkKernelCompatibility: ~0.x constraint =====

assert_true(
    ExtensionDependencyResolver::checkKernelCompatibility('0.1.5', '~0.1.0'),
    '~ constraint: 0.1.5 in ~0.1.0 range'
);
assert_false(
    ExtensionDependencyResolver::checkKernelCompatibility('0.2.0', '~0.1.0'),
    '~ constraint: 0.2.0 not in ~0.1.0 range'
);

// ===== PluginManifest: valid kernel constraint =====

$manifestData = [
    'name' => 'test-plugin',
    'version' => '1.0.0',
    'requires' => ['kernel' => '>=2.0.0 <4.0.0'],
];
$manifest = new PluginManifest($manifestData);
assert_equal(
    'test-plugin',
    $manifest->name(),
    'Manifest with valid kernel constraint loads'
);
assert_equal(
    '>=2.0.0 <4.0.0',
    $manifest->minKernelVersion(),
    'Manifest stores valid kernel constraint'
);

// ===== PluginManifest: empty kernel constraint =====

$manifestData = [
    'name' => 'test-plugin',
    'version' => '1.0.0',
    'requires' => ['kernel' => ''],
];
$manifest = new PluginManifest($manifestData);
assert_equal(
    '',
    $manifest->minKernelVersion(),
    'Manifest with empty kernel constraint stores empty string'
);

// ===== PluginManifest: missing kernel constraint =====

$manifestData = [
    'name' => 'test-plugin',
    'version' => '1.0.0',
];
$manifest = new PluginManifest($manifestData);
assert_equal(
    '',
    $manifest->minKernelVersion(),
    'Manifest without kernel constraint stores empty string'
);

// ===== PluginManifest: invalid kernel constraint rejected =====

$invalidConstraints = ['abc', '>=abc', '^', '>>2.0.0', '>=2.0.0 abc', '|||'];
$rejected = false;
foreach ($invalidConstraints as $badConstraint) {
    $manifestData = [
        'name' => 'test-plugin',
        'version' => '1.0.0',
        'requires' => ['kernel' => $badConstraint],
    ];
    try {
        new PluginManifest($manifestData);
    } catch (PluginException $e) {
        $rejected = true;
    }
}
assert_true(
    $rejected,
    'PluginManifest rejects invalid kernel constraint'
);

// ===== PluginManifest: invalid kernel constraint rejected (detailed) =====

try {
    new PluginManifest([
        'name' => 'test-plugin',
        'version' => '1.0.0',
        'requires' => ['kernel' => 'invalid'],
    ]);
    assert_true(false, 'PluginManifest rejects invalid kernel (should throw)');
} catch (PluginException $e) {
    assert_contains(
        'invalid kernel requirement',
        strtolower($e->getMessage()),
        'PluginException message mentions invalid kernel requirement'
    );
}

summary();
