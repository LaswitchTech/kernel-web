<?php

/**
 * Migration runner tests.
 *
 * Tests MigrationRunner with an in-memory SQLite database and inline-created
 * migration files using the same class-naming convention as the kernel.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\MigrationRunner;
use App\Core\Migration;
use App\Core\DatabaseInterface;

// --- Bootstrap: create a temporary test environment ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Wrap PDO as DatabaseInterface for the runner
class TestDB implements DatabaseInterface
{
    public function __construct(private PDO $pdo) {}

    public function pdo(): PDO { return $this->pdo; }
    public function fetch(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function fetchOne(string $sql, array $params = []): ?array {
        $rows = $this->fetch($sql, $params);
        return $rows[0] ?? null;
    }
    public function execute(string $sql, array $bindings = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return (int) $stmt->rowCount();
    }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function lastInsertId(): string { return $this->pdo->lastInsertId(); }
}

$db = new TestDB($pdo);

// --- Test 1: pending() returns empty on fresh runner ---
$migrationsDir = sys_get_temp_dir() . '/kernel-test-migrations-' . uniqid();
@mkdir($migrationsDir, 0755, true);

$runner = new MigrationRunner($db, $migrationsDir);
assert_array_has_length($runner->pending(), 0, 'No pending migrations on empty dir');
assert_array_has_length($runner->applied(), 0, 'No applied migrations on fresh runner');

// --- Test 2: pending() discovers files ---
// MigrationRunner derives class names: strip leading digits+underscore, then StudlyCase.
// So 0001_kernel_test_a → KernelTestA
// Use require to define in global namespace
$mk = function(string $class, string $body): string {
    return "<?php class {$class} extends \\App\\Core\\Migration { {$body} }";
};
file_put_contents($migrationsDir . '/0001_kernel_test_a.php', $mk('KernelTestA', 'public function __construct($db) {} public function up(): void {} public function down(): void {}'));
file_put_contents($migrationsDir . '/0002_kernel_test_b.php', $mk('KernelTestB', 'public function __construct($db) {} public function up(): void {} public function down(): void {}'));

$pending = $runner->pending();
assert_array_has_length($pending, 2, 'Two pending migrations discovered');
// pending() returns full file paths — check basename via substring
assert_contains('0001_kernel_test_a', $pending[0], 'First pending is 0001');

// --- Test 3: run() executes pending migrations ---
// run() returns migration NAMES (basename without .php), not full paths.
$applied = $runner->run();
assert_array_has_length($applied, 2, 'Two migrations applied');
assert_contains('0001_kernel_test_a', $applied, 'First migration name in applied list');
assert_contains('0002_kernel_test_b', $applied, 'Second migration name in applied list');

// --- Test 4: pending() returns empty after run ---
assert_array_has_length($runner->pending(), 0, 'No pending migrations after run');

// --- Test 5: applied() returns applied names ---
$appliedNames = $runner->applied();
assert_array_has_length($appliedNames, 2, 'Two applied migrations recorded');

// --- Test 6: run() on already-run migrations returns empty ---
$emptyApplied = $runner->run();
assert_array_has_length($emptyApplied, 0, 'Run again returns empty array');

// --- Test 7: MigrationRunner class is in correct namespace ---
assert_class_exists(MigrationRunner::class, 'MigrationRunner class exists');

// --- Test 8: Migration base class exists ---
assert_class_exists(Migration::class, 'Migration base class exists');

// --- Test 9: Invalid migration class (not extending Migration) throws ---
// First create and apply a valid migration (0004) so it's recorded in the DB.
// We use a higher sort order so it doesn't interfere with Test 9's run() which
// will encounter the bad migration (0003) before reaching it.
$restoreFile = $migrationsDir . '/0004_restore_migrations_table.php';
$restoreContent = '<?php class RestoreMigrationsTable extends \\App\\Core\\Migration { public function __construct($db) {} public function up(): void {} public function down(): void {} }';
file_put_contents($restoreFile, $restoreContent);
(new MigrationRunner($db, $migrationsDir))->run();

// Now create the bad migration. Test 9's runner will apply it and throw.
file_put_contents($migrationsDir . '/0003_bad_migration.php', '<?php class BadMigration { public function up(): void {} public function down(): void {} }');

assert_raises(
    fn() => (new MigrationRunner($db, $migrationsDir))->run(),
    RuntimeException::class,
    'Invalid migration class throws RuntimeException'
);

// --- Test 10: Missing migration file throws on rollback ---
// Delete the bad migration file (created in Test 9) so it doesn't interfere.
@unlink($migrationsDir . '/0003_bad_migration.php');

// The restore migration was applied in Test 9 (before the bad one) and recorded in the DB.
// Delete its file — the record remains, so rollback will try to find a missing file.
@unlink($migrationsDir . '/0004_restore_migrations_table.php');

$runner2 = new MigrationRunner($db, $migrationsDir);

assert_raises(
    fn() => $runner2->rollback(),
    RuntimeException::class,
 '  Rollback missing file throws RuntimeException'
);

// --- Test 11: Migration class name derivation handles underscores and digits ---
// Regression test for the classFromName convention:
//   0001_add_password_reset_support → AddPasswordResetSupport
//   0002_add_2fa_support → Add2faSupport
$testDir = sys_get_temp_dir() . '/kernel-test-migrations-classname';
@mkdir($testDir, 0755, true);

$mk = function(string $class, string $body): string {
    return "<?php class {$class} extends \\App\\Core\\Migration { {$body} }";
};

// File: 0001_add_password_reset_support → expected class: AddPasswordResetSupport
file_put_contents($testDir . '/0001_add_password_reset_support.php',
    $mk('AddPasswordResetSupport', 'public function __construct($db) {} public function up(): void {} public function down(): void {}'));

// File: 0002_add_2fa_support → expected class: Add2faSupport
file_put_contents($testDir . '/0002_add_2fa_support.php',
    $mk('Add2faSupport', 'public function __construct($db) {} public function up(): void {} public function down(): void {}'));

// Both should apply without class-not-found errors.
$runner11 = new MigrationRunner(new TestDB(new PDO('sqlite::memory:')), $testDir);
$applied11 = $runner11->run();
assert_array_has_length($applied11, 2, 'Both underscore/digit migrations apply without class errors');
assert_contains('0001_add_password_reset_support', $applied11, 'add_password_reset_support resolves correctly');
assert_contains('0002_add_2fa_support', $applied11, 'add_2fa_support resolves correctly');

// --- Cleanup ---
array_map('unlink', glob($migrationsDir . '/*.php'));
rmdir($migrationsDir);
array_map('unlink', glob($testDir . '/*.php'));
rmdir($testDir);

// --- Summary ===
summary();
exit($__FAIL__ > 0 ? 1 : 0);
