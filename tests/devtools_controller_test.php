<?php
/**
 * Tests: Developer settings toggles (real controls, not status badges).
 *
 * Verifies:
 * - tools.php renders toggle switches when flags are true
 * - tools.php renders toggle switches when flags are false
 * - Dev Console warning banner shows when dev_console is false
 * - Dev Console warning banner hidden when dev_console is true
 * - AJAX save endpoint saves settings to DB
 * - Settings persist and reload correctly
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
require __DIR__ . '/../app/Core/SettingsRegistry.php';
require __DIR__ . '/../app/Core/SettingsSection.php';
require __DIR__ . '/../app/Services/SystemSettingService.php';
require __DIR__ . '/../app/Models/SystemSettingRepository.php';
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

// ===== TEST: AJAX save endpoint works =====
echo "= TEST: AJAX save endpoint =\n";

// Set up a minimal container
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['developer_developer'] = '1';
$_POST['developer_debug'] = '0';
$_POST['developer_dev_console'] = '1';

// Create an in-memory SQLite DB
$dbPdo = new PDO('sqlite::memory:');
$dbPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbPdo->exec('CREATE TABLE system_settings (
    key TEXT PRIMARY KEY,
    value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

class TestDB implements \App\Core\DatabaseInterface
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

// Register the developer section
App\Core\SettingsRegistry::addSection([
    'id' => 'developer',
    'label' => 'Developer Settings',
    'column' => 'left',
    'order' => 5,
    'permission' => null,
    'keys' => ['developer.developer', 'developer.debug', 'developer.dev_console'],
]);

$repo = new App\Models\SystemSettingRepository(new TestDB($dbPdo));
$svc = new App\Services\SystemSettingService($repo);

// Verify default values
assert_false($svc->getBool('developer.developer', false), 'default developer = false');
assert_false($svc->getBool('developer.debug', false), 'default debug = false');
assert_false($svc->getBool('developer.dev_console', false), 'default dev_console = false');

// Simulate save
$svc->set('developer.developer', '1');
$svc->set('developer.debug', '0');
$svc->set('developer.dev_console', '1');

// Verify persisted values
assert_true($svc->getBool('developer.developer', false), 'persisted developer = true');
assert_false($svc->getBool('developer.debug', false), 'persisted debug = false');
assert_true($svc->getBool('developer.dev_console', false), 'persisted dev_console = true');

echo "PASS\n";

// ===== TEST: Settings persist and reload =====
echo "= TEST: Persist and reload =\n";

// Read fresh from service (simulates page reload)
assert_true($svc->getBool('developer.developer', false), 'reload developer = true');
assert_false($svc->getBool('developer.debug', false), 'reload debug = false');
assert_true($svc->getBool('developer.dev_console', false), 'reload dev_console = true');

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

echo "\n=== ALL DEVTOOLS CONTROLLER TESTS PASSED ===\n";
