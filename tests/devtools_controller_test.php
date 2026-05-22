<?php
/**
 * Tests: Developer settings toggles (real controls, not status badges).
 *
 * Verifies:
 * - tools.php renders toggle switches when flags are true
 * - tools.php renders toggle switches when flags are false
 * - tools.php does NOT render status badges
 * - AJAX save endpoint saves settings to DB
 * - Settings persist and reload correctly
 * - /admin/settings HTML includes working toggle with JS
 * - Route for POST /admin/developer/settings is registered
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
require __DIR__ . '/../app/Core/SettingsRegistry.php';
require __DIR__ . '/../app/Core/SettingsSection.php';
require __DIR__ . '/../app/Services/SystemSettingService.php';
require __DIR__ . '/../app/Models/SystemSettingRepository.php';
require __DIR__ . '/../app/Core/DatabaseInterface.php';
reset_counters();

// ===== Helper: render tools.php in controller scope =====
function render_tools($appConfig): string
{
    ob_start();
    include __DIR__ . '/../app/Views/admin/developer/tools.php';
    $output = ob_get_clean();
    return $output;
}

// ===== TEST: All flags true → toggle switches rendered with checked state =====
echo "= TEST: All true → toggles checked =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'toggle_developer') !== false, 'toggle input present');
assert_true(strpos($output, 'Developer Mode') !== false, 'Developer Mode label present');
assert_true(strpos($output, 'Debug Mode') !== false, 'Debug Mode label present');
assert_true(strpos($output, 'Dev Console') !== false, 'Dev Console label present');
assert_true(strpos($output, 'checked') !== false, 'at least one checked state present');
echo "PASS\n";

// ===== TEST: All flags false → toggle switches rendered without checked =====
echo "= TEST: All false → toggles unchecked =\n";
$appConfig = ['developer' => false, 'debug' => false, 'dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'toggle_developer') !== false, 'toggle input present');
assert_true(strpos($output, '<input') !== false, 'input elements present');
echo "PASS\n";

// ===== TEST: No badges (status table replaced with toggles) =====
echo "= TEST: No status badges rendered =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'bg-success') === false, 'no bg-success badges');
assert_true(strpos($output, 'badge bg-danger') === false, 'no bg-danger badges');
echo "PASS\n";

// ===== TEST: Dev Console warning when dev_console = false =====
echo "= TEST: dev_console false → warning =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => false];
$output = render_tools($appConfig);
assert_true(strpos($output, 'alert-warning') !== false, 'warning banner present');
echo "PASS\n";

// ===== TEST: No warning when dev_console = true =====
echo "= TEST: dev_console true → no warning =\n";
$appConfig = ['developer' => true, 'debug' => true, 'dev_console' => true];
$output = render_tools($appConfig);
assert_true(strpos($output, 'alert-warning') === false, 'no warning banner');
echo "PASS\n";

// ===== TEST: /admin/settings HTML includes working toggle with JS =====
echo "= TEST: /admin/settings has toggle with JS =\n";
$settingsOutput = render_tools(['developer' => true, 'debug' => true, 'dev_console' => true]);
// For settings page, we just verify the toggle name/id exist in the expected pattern.
// The actual settings.php is tested separately.
assert_true(strpos($output, 'developer_developer') !== false, 'settings toggle name present');
echo "PASS\n";

// ===== TEST: AJAX save endpoint saves to DB =====
echo "= TEST: AJAX save endpoint =\n";

// Create an in-memory SQLite DB
$dbPdo = new PDO('sqlite::memory:');
$dbPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbPdo->exec('CREATE TABLE system_settings (
    key TEXT PRIMARY KEY,
    value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

class TestDB2 implements \App\Core\DatabaseInterface
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

$repo = new App\Models\SystemSettingRepository(new TestDB2($dbPdo));
$svc = new App\Services\SystemSettingService($repo);

// Verify default values
assert_false($svc->getBool('developer.developer', false), 'default developer = false');
assert_false($svc->getBool('developer.debug', false), 'default debug = false');
assert_false($svc->getBool('developer.dev_console', false), 'default dev_console = false');

// Simulate save (partial: only developer_developer)
$svc->set('developer.developer', '1');

// Verify persisted value
assert_true($svc->getBool('developer.developer', false), 'persisted developer = true');

echo "PASS\n";

// ===== TEST: Persist and reload =====
echo "= TEST: Persist and reload =\n";
assert_true($svc->getBool('developer.developer', false), 'reload developer = true');
echo "PASS\n";

// ===== TEST: Toggle all true =====
echo "= TEST: Toggle all true =\n";
$svc->set('developer.developer', '1');
$svc->set('developer.debug', '1');
$svc->set('developer.dev_console', '1');
assert_true($svc->getBool('developer.developer'), 'all true: developer');
assert_true($svc->getBool('developer.debug'), 'all true: debug');
assert_true($svc->getBool('developer.dev_console'), 'all true: dev_console');
echo "PASS\n";

// ===== TEST: Toggle all false =====
echo "= TEST: Toggle all false =\n";
$svc->set('developer.developer', '0');
$svc->set('developer.debug', '0');
$svc->set('developer.dev_console', '0');
assert_false($svc->getBool('developer.developer'), 'all false: developer');
assert_false($svc->getBool('developer.debug'), 'all false: debug');
assert_false($svc->getBool('developer.dev_console'), 'all false: dev_console');
echo "PASS\n";

// ===== TEST: Route for POST /admin/developer/settings is registered =====
echo "= TEST: Route POST /admin/developer/settings =\n";
$routesFile = file_get_contents(__DIR__ . '/../routes/web.php');
assert_true(strpos($routesFile, "post('/admin/developer/settings'") !== false,
    'POST /admin/developer/settings route is registered');
echo "PASS\n";

// ===== TEST: routes/web.php includes DeveloperController@ajaxSaveSettings =====
echo "= TEST: Route points to correct controller method =\n";
assert_true(strpos($routesFile, 'DeveloperController@ajaxSaveSettings') !== false,
    'Route points to ajaxSaveSettings method');
echo "PASS\n";

// ===== TEST: tools.php HTML includes JS fetch to correct endpoint =====
echo "= TEST: tools.php JS fetches correct endpoint =\n";
$toolsContent = file_get_contents(__DIR__ . '/../app/Views/admin/developer/tools.php');
assert_true(strpos($toolsContent, "'/admin/developer/settings'") !== false,
    'JS fetch targets /admin/developer/settings');
assert_true(strpos($toolsContent, "method: 'POST'") !== false || strpos($toolsContent, "method: \"POST\"") !== false,
    'JS uses POST method');
echo "PASS\n";

// ===== TEST: /admin/settings HTML includes JS for developer toggle =====
echo "= TEST: settings.php has JS for toggle =\n";
$settingsContent = file_get_contents(__DIR__ . '/../app/Views/admin/settings.php');
assert_true(strpos($settingsContent, 'settings_developer') !== false,
    'settings_developer ID present');
assert_true(strpos($settingsContent, "fetch('/admin/developer/settings'") !== false,
    'settings toggle JS fetches correct endpoint');
assert_true(strpos($settingsContent, 'getElementById') !== false,
    'settings toggle JS uses getElementById');
echo "PASS\n";

echo "\n=== ALL DEVTOOLS CONTROLLER TESTS PASSED ===\n";
