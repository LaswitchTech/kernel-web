<?php
/**
 * Scope test: DevTools view receives $appConfig in controller scope
 * (the same scope used before layout includes).
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

// Flat config (real app path after Env::load + Config::load)
$flatConfig = [
    'name' => 'Kernel-Web',
    'developer' => true,
    'debug' => true,
    'dev_console' => true,
];

// FIXED: Controller derives $appConfig same as ViewGlobals
$appConfig = is_array($flatConfig['app'] ?? null) ? $flatConfig['app'] : $flatConfig;

ob_start();
include __DIR__ . '/../app/Views/admin/developer/tools.php';
$output = ob_get_clean();

assert_true(strpos($output, 'badge bg-success') !== false, 'On badges present');
assert_true(strpos($output, 'true') !== false, 'true values present');
assert_true(strpos($output, 'Off') === false, 'no Off badges when all true');
echo "PASS\n";

echo "\n=== DEVTOOLS SCOPE TEST PASSED ===\n";
