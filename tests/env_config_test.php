<?php
/**
 * Focused test: env config loading for developer/debug/dev_console flags.
 *
 * Proves:
 * - Env::get() reads from loaded .env (not just getenv())
 * - config/app.php resolves boolean flags correctly
 * - Truthy values: 'true', '1', 'yes', 'on' → true
 * - Falsy values: 'false', '0', 'no', 'off', '' → false
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Env;

// ── Helper: create temp .env, load it, include config/app.php ──
function loadConfigFromEnv(array $entries): array
{
    // Create temp .env
    $path = sys_get_temp_dir() . '/kernel-web-env-test-' . uniqid() . '.env';
    $lines = [];
    foreach ($entries as $k => $v) {
        $lines[] = "{$k}={$v}";
    }
    file_put_contents($path, implode("\n", $lines) . "\n");

    // Reset Env singleton (Env::load is single-shot, so we must reset)
    $ref = new ReflectionClass(Env::class);
    $loadedProp = $ref->getProperty('loaded');
    $loadedProp->setAccessible(true);
    $loadedProp->setValue(null, false);

    $valuesProp = $ref->getProperty('values');
    $valuesProp->setAccessible(true);
    $valuesProp->setValue(null, []);

    // Load
    Env::load($path);

    // Include config/app.php which defines env() and env_bool()
    $configPath = __DIR__ . '/../config/app.php';
    $config = include $configPath;

    // Cleanup
    unlink($path);

    return $config;
}

// ===== TEST: All three flags = true =====
echo "= TEST: APP_DEVELOPER=true, APP_DEBUG=true, APP_DEV_CONSOLE=true =\n";
$config = loadConfigFromEnv([
    'APP_DEVELOPER' => 'true',
    'APP_DEBUG' => 'true',
    'APP_DEV_CONSOLE' => 'true',
]);
assert_true($config['developer'], 'developer = true');
assert_true($config['debug'], 'debug = true');
assert_true($config['dev_console'], 'dev_console = true');
echo "PASS\n";

// ===== TEST: All three flags = false =====
echo "= TEST: All three flags = false =\n";
$config = loadConfigFromEnv([
    'APP_DEVELOPER' => 'false',
    'APP_DEBUG' => 'false',
    'APP_DEV_CONSOLE' => 'false',
]);
assert_false($config['developer'], 'developer = false');
assert_false($config['debug'], 'debug = false');
assert_false($config['dev_console'], 'dev_console = false');
echo "PASS\n";

// ===== TEST: Mixed — only dev_console = true =====
echo "= TEST: Only dev_console = true, others absent =\n";
$config = loadConfigFromEnv([
    'APP_DEV_CONSOLE' => 'true',
]);
assert_false($config['developer'], 'developer defaults to false');
assert_true($config['debug'], 'debug defaults to true');
assert_true($config['dev_console'], 'dev_console = true');
echo "PASS\n";

// ===== TEST: Env::get() reads from .env store =====
echo "= TEST: Env::get() reads loaded values =\n";
$ref = new ReflectionClass(Env::class);
$valuesProp = $ref->getProperty('values');
$valuesProp->setAccessible(true);
$values = $valuesProp->getValue(null);
assert_true(isset($values['APP_DEV_CONSOLE']), 'APP_DEV_CONSOLE stored in Env');
assert_equal('true', $values['APP_DEV_CONSOLE'], 'APP_DEV_CONSOLE value is "true"');
echo "PASS\n";

// ===== TEST: Truthy variants → true =====
$truthyVariants = ['true', '1', 'yes', 'on', 'TRUE', 'True', '1'];
foreach ($truthyVariants as $variant) {
    echo "= TEST: Truthy variant '{$variant}' =\n";
    $config = loadConfigFromEnv(['APP_DEVELOPER' => $variant]);
    assert_true($config['developer'], "'{$variant}' resolves to true");
    echo "PASS\n";
}

// ===== TEST: Falsy variants → false =====
$falsyVariants = ['false', '0', 'no', 'off', ''];
foreach ($falsyVariants as $variant) {
    $label = $variant === '' ? '(empty)' : "'{$variant}'";
    echo "= TEST: Falsy variant {$label} =\n";
    $config = loadConfigFromEnv(['APP_DEVELOPER' => $variant]);
    assert_false($config['developer'], "{$label} resolves to false");
    echo "PASS\n";
}

// ===== TEST: Missing env var → uses default =====
echo "= TEST: Missing APP_DEBUG → defaults to true =\n";
$config = loadConfigFromEnv([]);
assert_true($config['debug'], 'debug defaults to true when absent');
assert_false($config['developer'], 'developer defaults to false when absent');
echo "PASS\n";

echo "\n=== ALL ENV CONFIG TESTS PASSED ===\n";
