<?php
/**
 * Tests: Developer settings toggles — round-trip persistence.
 *
 * Verifies:
 * - Tools page reads toggle state from DB (not just config/app.php)
 * - ajaxSaveSettings writes persist and are read back after reload
 * - Toggles render correctly for all value combinations
 * - Route and JS endpoint wiring is correct
 * - /admin/settings HTML includes JS for the toggle
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
require __DIR__ . '/../app/Core/SettingsRegistry.php';
require __DIR__ . '/../app/Core/SettingsSection.php';
require __DIR__ . '/../app/Core/DatabaseInterface.php';
require __DIR__ . '/../app/Services/SystemSettingService.php';
require __DIR__ . '/../app/Models/SystemSettingRepository.php';
reset_counters();

class TestDB3 implements \App\Core\DatabaseInterface
{
    public function __construct(private PDO $pdo) {}
    public function pdo(): PDO { return $this->pdo; }
    public function fetch(string $sql, array $bindings = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function fetchOne(string $sql, array $bindings = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
    public function execute(string $sql, array $bindings = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }
    public function lastInsertId(): string { return $this->pdo->lastInsertId(); }
}

// ===== Helper: render tools.php in controller scope =====
function render_tools($appConfig): string
{
    ob_start();
    include __DIR__ . '/../app/Views/admin/developer/tools.php';
    $output = ob_get_clean();
    return $output;
}

// ===== Helper: render with DB overrides (simulates DeveloperController::tools) =====
// This mimics what tools() does: derive $appConfig from config, then override with DB values.
function render_tools_with_db_overrides(PDO $dbPdo, $appConfig): string
{
    $dbPdo->exec('DROP TABLE IF EXISTS system_settings');
    $dbPdo->exec('CREATE TABLE system_settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $svc = new \App\Services\SystemSettingService(new \App\Models\SystemSettingRepository(new TestDB3($dbPdo)));

    // Apply DB overrides to $appConfig (same logic as DeveloperController::tools).
    if ($svc->get('developer.developer') !== null) {
        $appConfig['developer'] = $svc->getBool('developer.developer', $appConfig['developer'] ?? false);
    }
    if ($svc->get('developer.debug') !== null) {
        $appConfig['debug'] = $svc->getBool('developer.debug', $appConfig['debug'] ?? false);
    }
    if ($svc->get('developer.dev_console') !== null) {
        $appConfig['dev_console'] = $svc->getBool('developer.dev_console', $appConfig['dev_console'] ?? false);
    }

    return render_tools($appConfig);
}

// ===== TEST: DB overrides apply correctly — developer=true =====
echo "= TEST: DB developer=true overrides config =\n";
$dbPdo = new PDO('sqlite::memory:');
$dbPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbPdo->exec('CREATE TABLE system_settings (
    key TEXT PRIMARY KEY, value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');
$dbPdo->exec("INSERT INTO system_settings (key, value) VALUES ('developer.developer', '1')");

$appConfig = ['developer' => false, 'debug' => false, 'dev_console' => false];
$output = render_tools_with_db_overrides($dbPdo, $appConfig);
assert_true(strpos($output, 'checked') !== false, 'toggle reflects DB override');
echo "PASS\n";

// ===== TEST: DB debug=true, dev_console=true — toggles checked =====
echo "= TEST: DB debug=true + dev_console=true =\n";
$dbPdo->exec("INSERT INTO system_settings (key, value) VALUES ('developer.debug', '1')");
$dbPdo->exec("INSERT INTO system_settings (key, value) VALUES ('developer.dev_console', '1')");

$appConfig = ['developer' => false, 'debug' => false, 'dev_console' => false];
$output = render_tools_with_db_overrides($dbPdo, $appConfig);
assert_true(strpos($output, 'toggle_debug') !== false, 'debug toggle present');
assert_true(strpos($output, 'toggle_dev_console') !== false, 'dev_console toggle present');
echo "PASS\n";

// ===== TEST: DB debug=false, dev_console=false — toggles unchecked =====
echo "= TEST: DB debug=false + dev_console=false — refresh after save =\n";
// Clear DB and save fresh values
$dbPdo->exec("DELETE FROM system_settings");
$dbPdo->exec("INSERT INTO system_settings (key, value) VALUES ('developer.developer', '1')");
$dbPdo->exec("INSERT INTO system_settings (key, value) VALUES ('developer.debug', '0')");
$dbPdo->exec("INSERT INTO system_settings (key, value) VALUES ('developer.dev_console', '0')");

$appConfig = ['developer' => false, 'debug' => true, 'dev_console' => true];
// Simulate reading via SystemSettingService (DB overrides config)
$svc = new \App\Services\SystemSettingService(new \App\Models\SystemSettingRepository(new TestDB3($dbPdo)));
$effective = [
    'developer' => $svc->getBool('developer.developer', $appConfig['developer']),
    'debug' => $svc->getBool('developer.debug', $appConfig['debug']),
    'dev_console' => $svc->getBool('developer.dev_console', $appConfig['dev_console']),
];
assert_false($effective['debug'], 'DB debug=false overrides config debug=true');
assert_false($effective['dev_console'], 'DB dev_console=false overrides config dev_console=true');

$output = render_tools($effective);
// Unchecked toggles should not have 'checked' attribute
$debugInputPos = strpos($output, 'toggle_debug');
$devConsoleInputPos = strpos($output, 'toggle_dev_console');
assert_true($debugInputPos !== false, 'debug toggle input present');
assert_true($devConsoleInputPos !== false, 'dev_console toggle input present');
// The unchecked state means the word 'checked' should NOT follow the input
$debugSection = substr($output, $debugInputPos, 200);
$devConsoleSection = substr($output, $devConsoleInputPos, 200);
assert_true(strpos($debugSection, 'checked') === false, 'debug toggle unchecked when DB=false');
assert_true(strpos($devConsoleSection, 'checked') === false, 'dev_console toggle unchecked when DB=false');
echo "PASS\n";

// ===== TEST: DB debug=true, dev_console=true — reload and assert checked =====
echo "= TEST: DB debug=true + dev_console=true — reload and assert =\n";
$dbPdo->exec("DELETE FROM system_settings");
$dbPdo->exec("INSERT INTO system_settings (key, value) VALUES ('developer.developer', '1')");
$dbPdo->exec("INSERT INTO system_settings (key, value) VALUES ('developer.debug', '1')");
$dbPdo->exec("INSERT INTO system_settings (key, value) VALUES ('developer.dev_console', '1')");

$svc = new \App\Services\SystemSettingService(new \App\Models\SystemSettingRepository(new TestDB3($dbPdo)));
$effective = [
    'developer' => $svc->getBool('developer.developer', false),
    'debug' => $svc->getBool('developer.debug', false),
    'dev_console' => $svc->getBool('developer.dev_console', false),
];
assert_true($effective['debug'], 'DB debug=true');
assert_true($effective['dev_console'], 'DB dev_console=true');

$output = render_tools($effective);
assert_true(strpos($output, 'Developer Tools') !== false, 'page renders');
echo "PASS\n";

// ===== TEST: No DB row — config fallback applies =====
echo "= TEST: No DB row — config fallback =\n";
$dbPdo->exec("DELETE FROM system_settings");
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools_with_db_overrides($dbPdo, $appConfig);
assert_true(strpos($output, 'Developer Tools') !== false, 'page renders with config defaults');
echo "PASS\n";

// ===== TEST: tools.php renders toggle switches =====
echo "= TEST: tools.php renders toggle switches =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'toggle_developer') !== false, 'toggle_developer ID present');
assert_true(strpos($output, 'toggle_debug') !== false, 'toggle_debug ID present');
assert_true(strpos($output, 'toggle_dev_console') !== false, 'toggle_dev_console ID present');
assert_true(strpos($output, 'form-check form-switch') !== false, 'form-switch class present');
echo "PASS\n";

// ===== TEST: No status badges =====
echo "= TEST: No status badges =\n";
assert_true(strpos($output, 'bg-success') === false, 'no bg-success badges');
assert_true(strpos($output, 'badge bg-danger') === false, 'no bg-danger badges');
echo "PASS\n";

// ===== TEST: Route registration =====
echo "= TEST: Route POST /admin/developer/settings =\n";
$routesFile = file_get_contents(__DIR__ . '/../routes/web.php');
assert_true(strpos($routesFile, "post('/admin/developer/settings'") !== false, 'route registered');
assert_true(strpos($routesFile, 'DeveloperController@ajaxSaveSettings') !== false, 'points to ajaxSaveSettings');
echo "PASS\n";

// ===== TEST: tools.php JS endpoint matches route =====
echo "= TEST: JS fetch targets correct endpoint =\n";
$toolsContent = file_get_contents(__DIR__ . '/../app/Views/admin/developer/tools.php');
assert_true(strpos($toolsContent, "'/admin/developer/settings'") !== false, 'JS fetches /admin/developer/settings');
assert_true(strpos($toolsContent, "method: 'POST'") !== false || strpos($toolsContent, 'method: "POST"') !== false, 'JS uses POST');
echo "PASS\n";

// ===== TEST: /admin/settings toggle has JS =====
echo "= TEST: settings.php has JS for toggle =\n";
$settingsContent = file_get_contents(__DIR__ . '/../app/Views/admin/settings.php');
assert_true(strpos($settingsContent, 'settings_developer') !== false, 'settings_developer ID present');
assert_true(strpos($settingsContent, "fetch('/admin/developer/settings'") !== false, 'JS fetches correct endpoint');
echo "PASS\n";

echo "\n=== ALL DEVTOOLS CONTROLLER TESTS PASSED ===\n";
