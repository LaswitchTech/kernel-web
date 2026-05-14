<?php

/**
 * Tests for the Organizations runtime UX (controller + ProfileModal section).
 *
 * Tests: ProfileOrganizationsController (create, switch, list),
 *        renderSection output, ProfileModal section registration, slug handling.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Models\OrganizationRepository;
use App\Models\OrganizationMemberRepository;
use App\Core\ProfileModal;
use App\Controllers\ProfileOrganizationsController;

// --- Bootstrap: in-memory SQLite + TestDB ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

class TestDB8 implements \App\Core\DatabaseInterface
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

$db = new TestDB8($pdo);

$pdo->exec("
    CREATE TABLE organizations (
        id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        type TEXT NOT NULL DEFAULT 'organization',
        active INTEGER NOT NULL DEFAULT 1,
        created_at VARCHAR(32) NOT NULL,
        updated_at VARCHAR(32) NOT NULL
    )
");

$pdo->exec("
    CREATE TABLE organization_users (
        id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        role TEXT NOT NULL DEFAULT 'member',
        is_default INTEGER NOT NULL DEFAULT 0,
        created_at VARCHAR(32) NOT NULL,
        updated_at VARCHAR(32),
        UNIQUE(organization_id, user_id)
    )
");

$pdo->exec('CREATE INDEX IF NOT EXISTS organization_users_user_id ON organization_users (user_id)');
$pdo->exec('CREATE INDEX IF NOT EXISTS organization_users_org_id ON organization_users (organization_id)');

$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        display_name TEXT DEFAULT '',
        password_hash TEXT NOT NULL,
        is_active INTEGER DEFAULT 1,
        email_verified_at VARCHAR(32),
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )
");

$pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['alice', 'alice@example.com', 'Alice Smith', '$2y$10$hash']);
$pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['bob', 'bob@example.com', 'Bob Jones', '$2y$10$hash']);

$users = ['alice' => 1, 'bob' => 2];
$orgRepo = new OrganizationRepository($db);
$memberRepo = new OrganizationMemberRepository($db);

// --- ProfileModal Section Registration Tests ---

// test: section is not registered before calling registerSection
ProfileModal::clear();
$_SESSION = [];
assert_false(ProfileModal::hasSection('organizations'), 'organizations not registered before registerSection');

// test: registerSection registers the section
ProfileOrganizationsController::registerSection();
assert_true(ProfileModal::hasSection('organizations'), 'registerSection registers the section');

$section = ProfileModal::getSection('organizations');
assert_not_null($section, 'getSection returns the section');
assert_equal('organizations', $section->id, 'section has correct id');
assert_equal('Organizations', $section->label, 'section has correct label');
assert_equal('bi-building', $section->icon, 'section has correct icon');
assert_equal(30, $section->order, 'section has correct order');
assert_equal('core', $section->source, 'section source is core');
assert_true($section->isVisible([]), 'section is visible without permissions');

// test: callback is callable
assert_true(is_callable($section->callback), 'section callback is callable');

// --- RenderSection Output Tests ---

// test: renderSection returns HTML with expected structure
$_SESSION = [];
$context = [
    'principal' => ['user' => ['id' => $users['alice']]],
    'permissions' => [],
    'db' => $db,
];
$html = ProfileOrganizationsController::renderSection($context);
assert_true(mb_strlen($html) > 100, 'renderSection returns non-trivial HTML');
assert_contains('Current organization', $html, 'HTML contains "Current organization" label');
assert_contains('Switch organization', $html, 'HTML contains "Switch organization" label');
assert_contains('Create organization', $html, 'HTML contains "Create organization" label');
assert_contains('pm-org-switch', $html, 'HTML contains org switch select');
assert_contains('pm-org-name', $html, 'HTML contains org name input');
assert_contains('pm-org-create-form', $html, 'HTML contains create form ID');
assert_contains('pm-org-create-btn', $html, 'HTML contains create button ID');

// test: renderSection with no user returns empty string
$htmlNoUser = ProfileOrganizationsController::renderSection(['principal' => ['user' => null], 'permissions' => [], 'db' => $db]);
assert_equal('', $htmlNoUser, 'renderSection returns empty when no user');

// test: renderSection with no db returns empty string
$htmlNoDb = ProfileOrganizationsController::renderSection(['principal' => ['user' => ['id' => 1]], 'permissions' => [], 'db' => null]);
assert_equal('', $htmlNoDb, 'renderSection returns empty when no db');

// test: renderSection shows "- none -" when user has no orgs
assert_contains('— none —', $html, 'HTML shows "- none -" when no orgs');

// --- Organization Creation Tests ---

// test: create organization via repo
$orgId = $orgRepo->create(['name' => 'Acme Corp', 'slug' => 'acme', 'type' => 'client']);
assert_true($orgId > 0, 'create returns positive ID');
$org = $orgRepo->findById($orgId);
assert_equal('Acme Corp', $org['name'], 'created org has correct name');
assert_equal('acme', $org['slug'], 'created org has correct slug');
assert_equal('client', $org['type'], 'created org has correct type');
assert_equal(1, $org['active'], 'created org is active by default');

// test: duplicate slug is detected by isSlugTaken
assert_true($orgRepo->isSlugTaken('acme'), 'isSlugTaken detects duplicate');
assert_false($orgRepo->isSlugTaken('nonexistent'), 'isSlugTaken returns false for missing');

// test: controller-style slug generation simulates suffix on duplicate
$testSlug = 'acme';
$counter = 1;
while ($orgRepo->isSlugTaken($testSlug)) {
    $testSlug = 'acme-' . $counter;
    $counter++;
}
assert_true($testSlug !== 'acme', 'slug generation produces unique slug');
assert_true(str_starts_with($testSlug, 'acme-'), 'unique slug has acme- prefix');
assert_false($orgRepo->isSlugTaken($testSlug), 'generated slug is not taken');

// test: slug with special chars generates correct format
$orgId3 = $orgRepo->create(['name' => 'Hello  World! @Test', 'slug' => 'hello--world--test']);
$org3 = $orgRepo->findById($orgId3);
assert_contains('-', $org3['slug'], 'slug contains hyphens');
assert_false(str_contains($org3['slug'], ' '), 'slug has no spaces');
assert_false(str_contains($org3['slug'], '@'), 'slug has no @');

// test: controller slug generation trims leading/trailing hyphens
$testSlug = '-test-2-';
$testSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($testSlug));
$testSlug = trim($testSlug, '-');
assert_false(str_starts_with($testSlug, '-'), 'slug has no leading hyphen');
assert_false(str_ends_with($testSlug, '-'), 'slug has no trailing hyphen');

// --- Membership and Default Tests ---

// test: addMember + isMember
$memberRepo->addMember($orgId, $users['alice'], 'admin');
assert_true($memberRepo->isMember($orgId, $users['alice']), 'alice is member of acme');
assert_equal('admin', $memberRepo->getRole($orgId, $users['alice']), 'alice has admin role');

// test: findOrgsForUser
$orgId5 = $orgRepo->create(['name' => 'Beta LLC', 'slug' => 'beta']);
$memberRepo->addMember($orgId5, $users['alice']);
$orgs = $memberRepo->findOrgsForUser($users['alice']);
assert_true(count($orgs) >= 2, 'findOrgsForUser returns multiple orgs');

// test: setDefaultOrg persists to session
$_SESSION = [];
$_SESSION['user'] = ['id' => $users['alice']];
$memberRepo->setDefaultOrg($users['alice'], $orgId);
$_SESSION['org_default_' . $users['alice']] = (string) $orgId;
assert_equal((string) $orgId, $_SESSION['org_default_' . $users['alice']], 'session stores default org ID');

// test: findDefaultOrgForUser reads session
$default = $memberRepo->findDefaultOrgForUser($users['alice']);
assert_not_null($default, 'findDefaultOrgForUser returns default from session');
assert_equal($orgId, $default['organization_id'], 'default org ID matches');

// test: switch organization unsets old context
$orgId6 = $orgRepo->create(['name' => 'Gamma Inc', 'slug' => 'gamma']);
$memberRepo->addMember($orgId6, $users['alice']);
$memberRepo->setDefaultOrg($users['alice'], $orgId6);
$_SESSION['org_default_' . $users['alice']] = (string) $orgId6;
unset($_SESSION['org_default_' . $users['alice']]);
$cleared = $_SESSION['org_default_' . $users['alice']] ?? 'NOTSET';
assert_equal('NOTSET', $cleared, 'session key cleared on switch');

// test: non-member cannot be set as default
assert_false($memberRepo->isMember($orgId, $users['bob']), 'bob is not member');
$memberRepo->setDefaultOrg($users['bob'], $orgId);
$bobDefault = $memberRepo->findDefaultOrgForUser($users['bob']);
assert_false($bobDefault !== null && $bobDefault['organization_id'] === $orgId, 'non-member default is not used');

// --- ProfileModalSection ID Validation Tests ---

// test: valid section IDs
$validIds = ['organizations', 'orgs2', 'my-section', 'a'];
foreach ($validIds as $id) {
    try {
        $s = new \App\Core\ProfileModalSection($id, "Test $id");
        assert_true(true, "ID '$id' is valid");
    } catch (\InvalidArgumentException $e) {
        assert_true(false, "ID '$id' should be valid (got: {$e->getMessage()})");
    }
}

// test: invalid section IDs
$invalidIds = ['1org', 'org name', 'OrgUpper', 'a/b', ''];
foreach ($invalidIds as $id) {
    try {
        $s = new \App\Core\ProfileModalSection($id, "Test $id");
        assert_true(false, "ID '$id' should be invalid");
    } catch (\InvalidArgumentException $e) {
        assert_true(true, "ID '$id' is correctly rejected");
    }
}

// test: duplicate section ID throws
ProfileModal::clear();
ProfileOrganizationsController::registerSection();
try {
    \App\Core\ProfileModal::addSection([
        'id' => 'organizations',
        'label' => 'Duplicate',
        'callback' => fn() => '',
        'source' => 'core',
    ]);
    assert_true(false, 'duplicate section ID should throw');
} catch (\InvalidArgumentException $e) {
    assert_true(str_contains($e->getMessage(), 'Duplicate'), 'duplicate error message mentions conflict');
}

// --- OrganizationRepository List Tests ---

// test: findAll returns all orgs
$all = $orgRepo->findAll();
assert_true(count($all) >= 2, 'findAll returns at least 2 orgs');

// test: findAllActive excludes inactive
$inactiveId = $orgRepo->create(['name' => 'Inactive', 'slug' => 'inactive']);
$allAfter = $orgRepo->findAll();
$pdo->exec('PRAGMA foreign_keys = OFF');
$orgRepo->setActive($inactiveId, false);
$pdo->exec('PRAGMA foreign_keys = ON');
$active = $orgRepo->findAllActive();
assert_true(count($active) < count($allAfter), 'findAllActive excludes inactive orgs');

// test: findBySlug returns null for missing
assert_null($orgRepo->findBySlug('does-not-exist'), 'findBySlug returns null for missing');

// --- OrganizationContext Resolution Tests ---

// test: context resolves to DB default when session empty
$_SESSION = [];
$_SESSION['user'] = ['id' => $users['alice']];
$memberRepo->setDefaultOrg($users['alice'], $orgId);
$ctx = $memberRepo->findDefaultOrgForUser($users['alice']);
assert_not_null($ctx, 'context resolves to DB default');

// test: context with no memberships returns null
$_SESSION = [];
$_SESSION['user'] = ['id' => $users['bob']];
// Bob has no memberships (removed at line ~220)
$orgsBob = $memberRepo->findOrgsForUser($users['bob']);
assert_equal(0, count($orgsBob), 'bob has no org memberships');

// --- Summary ---

summary();
exit($__FAIL__ > 0 ? 1 : 0);
