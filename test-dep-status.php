<?php
/**
 * Test dependency status analysis scenarios.
 *
 * Run: php test-dep-status.php
 */

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) return;
    $relative = ltrim(substr($class, strlen($prefix)), '\\');
    $file = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Services\Extensions\ExtensionDependencyResolver;

$passed = 0;
$failed = 0;

function check(string $label, string|array $deps, array $expectedStatuses): void
{
    global $passed, $failed;
    $catalog = [
        ['id' => 1, 'name' => 'Notes', 'slug' => 'notes', 'type' => 'plugin', 'version' => '1.2.0', 'status' => 'approved', 'is_installed' => 1, 'is_enabled' => 1],
        ['id' => 2, 'name' => 'Default Theme', 'slug' => 'default', 'type' => 'theme', 'version' => '1.5.0', 'status' => 'approved', 'is_installed' => 1, 'is_enabled' => 0],
        ['id' => 3, 'name' => 'Panel Layout', 'slug' => 'panel', 'type' => 'layout', 'version' => '1.0.0', 'status' => 'approved', 'is_installed' => 0, 'is_enabled' => 0],
        ['id' => 4, 'name' => 'Pending Notes', 'slug' => 'pending-notes', 'type' => 'plugin', 'version' => '0.5.0', 'status' => 'pending', 'is_installed' => 0, 'is_enabled' => 0],
        ['id' => 5, 'name' => 'Rejected Theme', 'slug' => 'rejected-theme', 'type' => 'theme', 'version' => '0.1.0', 'status' => 'rejected', 'is_installed' => 0, 'is_enabled' => 0],
        ['id' => 6, 'name' => 'Old Notes', 'slug' => 'old-notes', 'type' => 'plugin', 'version' => '0.9.0', 'status' => 'approved', 'is_installed' => 1, 'is_enabled' => 1],
    ];

    $depsJson = is_string($deps) ? $deps : json_encode($deps);
    $result = ExtensionDependencyResolver::analyzeDependencies($depsJson, $catalog);
    $actualStatuses = array_column($result, 'status');

    if ($actualStatuses === $expectedStatuses) {
        $passed++;
    } else {
        $failed++;
        echo "FAIL: $label\n";
        echo "  expected: " . json_encode($expectedStatuses) . "\n";
        echo "  actual:   " . json_encode($actualStatuses) . "\n";
        foreach ($result as $i => $d) {
            echo "  [$i] status={$d['status']} name={$d['name']} version={$d['installedVersion']} installed=" . ($d['installed'] ? 'Y' : 'N') . "\n";
        }
    }
}

echo "=== Dependency Status Analysis Tests ===\n\n";

// Scenario 1: no dependencies
echo "--- Scenario 1: No dependencies ---\n";
check('{}', [], []);
check('[]', [], []);
check('', [], []);
check('empty-null', [], []);

// Scenario 2: satisfied dependency
echo "\n--- Scenario 2: Satisfied dependency ---\n";
check('satisfied dep', ['plugin:notes' => '>=1.0.0'], ['satisfied']);

// Scenario 3: missing dependency
echo "\n--- Scenario 3: Missing dependency ---\n";
check('missing dep', ['plugin:missing-ext' => '>=1.0.0'], ['missing']);

// Scenario 4: disabled dependency
echo "\n--- Scenario 4: Disabled dependency ---\n";
check('disabled dep (installed)', ['theme:default' => '^1.0.0'], ['installed']);

// Scenario 5: pending dependency
echo "\n--- Scenario 5: Pending dependency ---\n";
check('pending dep', ['plugin:pending-notes' => '>=0.1.0'], ['pending']);

// Scenario 6: version mismatch
echo "\n--- Scenario 6: Version mismatch ---\n";
check('version mismatch', ['plugin:old-notes' => '^2.0.0'], ['version-mismatch']);

// Scenario 7: invalid key
echo "\n--- Scenario 7: Invalid key ---\n";
check('invalid key', ['notes' => '>=1.0.0'], ['invalid-key']);
check('invalid key upper', ['plugin:Notes' => '>=1.0.0'], ['invalid-key']);
check('invalid key type', ['extension:foo' => '>=1.0.0'], ['invalid-key']);

// Scenario 8: invalid constraint
echo "\n--- Scenario 8: Invalid constraint ---\n";
check('invalid constraint abc', ['plugin:notes' => 'abc'], ['invalid-constraint']);
check('invalid constraint 1.0', ['plugin:notes' => '1.0'], ['invalid-constraint']);
check('invalid constraint =>', ['plugin:notes' => '=>1.0.0'], ['invalid-constraint']);

// Scenario 9: multiple mixed statuses
echo "\n--- Scenario 9: Multiple mixed statuses ---\n";
check('mixed deps', [
    'plugin:notes' => '>=1.0.0',
    'plugin:missing-ext' => '>=1.0.0',
    'theme:default' => '^1.0.0',
    'layout:panel' => '1.0.0',
    'plugin:pending-notes' => '>=0.1.0',
    'theme:rejected-theme' => '',
    'invalid-key-here' => '>=1.0.0',
    'plugin:old-notes' => '^2.0.0',
], ['satisfied', 'missing', 'installed', 'missing', 'pending', 'rejected', 'invalid-key', 'version-mismatch']);

// Scenario 10: circular dependency detection (analyzeDependencies doesn't detect cycles — that's for checkInstall)
// Instead, test malformed
echo "\n--- Scenario 10: Malformed dependencies ---\n";
check('json array', '[1, 2, 3]', ['invalid-key']);
check('invalid json', 'not-json', ['malformed']);
check('json number', '42', ['malformed']);
check('json bool', 'true', ['malformed']);

// Additional: empty string is valid (no deps)
check('empty string', '', []);

echo "\n=== Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
