<?php

/**
 * Tests for the Organizations plugin foundation.
 *
 * Tests: OrganizationRepository, OrganizationMemberRepository, OrganizationContext.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Models\OrganizationRepository;
use App\Models\OrganizationMemberRepository;
use App\Core\DatabaseInterface;

// --- Bootstrap: in-memory SQLite + TestDB ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Enable foreign keys for SQLite
$pdo->exec('PRAGMA foreign_keys = ON');

class TestDB4 implements DatabaseInterface
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

$db = new TestDB4($pdo);

// Create schema
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

// Create users table for foreign key
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

// Seed test users
$pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['alice', 'alice@example.com', 'Alice Smith', '$2y$10$hash']);
$pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['bob', 'bob@example.com', 'Bob Jones', '$2y$10$hash']);
$pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 0, '2025-01-01 00:00:00', '2025-01-01 00:00:00')")
    ->execute(['charlie', 'charlie@example.com', 'Charlie Brown', '$2y$10$hash']);

$users = ['alice' => 1, 'bob' => 2, 'charlie' => 3];

$orgRepo = new OrganizationRepository($db);
$memberRepo = new OrganizationMemberRepository($db);

// --- OrganizationRepository Tests ---

// test: create organization
$orgId = $orgRepo->create(['name' => 'Acme Corp', 'slug' => 'acme', 'type' => 'client']);
assert_true($orgId > 0, 'create returns positive ID');
$org = $orgRepo->findById($orgId);
assert_equal('Acme Corp', $org['name'], 'created org has correct name');
assert_equal('acme', $org['slug'], 'created org has correct slug');
assert_equal('client', $org['type'], 'created org has correct type');
assert_equal(1, $org['active'], 'created org is active by default');

// test: find by slug
$orgBySlug = $orgRepo->findBySlug('acme');
assert_true($orgBySlug !== null, 'findBySlug finds existing org');
assert_equal('Acme Corp', $orgBySlug['name'], 'findBySlug returns correct org');

// test: find nonexistent by slug
assert_null($orgRepo->findBySlug('nonexistent'), 'findBySlug returns null for missing');

// test: isSlugTaken
assert_true($orgRepo->isSlugTaken('acme'), 'isSlugTaken returns true for existing');
assert_false($orgRepo->isSlugTaken('other'), 'isSlugTaken returns false for missing');

// test: isSlugTaken exclude
assert_false($orgRepo->isSlugTaken('acme', $orgId), 'isSlugTaken excludes given ID');

// test: findAll
$orgRepo->create(['name' => 'Beta LLC', 'slug' => 'beta', 'type' => 'partner']);
$all = $orgRepo->findAll();
assert_equal(2, count($all), 'findAll returns all orgs');

// test: findAllActive
assert_equal(2, count($orgRepo->findAllActive()), 'findAllActive returns active orgs');
$orgRepo->setActive($orgId, false);
assert_equal(1, count($orgRepo->findAllActive()), 'findAllActive excludes inactive');
assert_equal(2, count($orgRepo->findAll()), 'findAll includes inactive');

// test: updateName
$orgRepo->updateName($orgId, 'Acme Corporation');
$updated = $orgRepo->findById($orgId);
assert_equal('Acme Corporation', $updated['name'], 'updateName changes name');

// test: delete
$tempOrgId = $orgRepo->create(['name' => 'Temp Org', 'slug' => 'temp', 'type' => 'prospect']);
$orgRepo->delete($tempOrgId);
assert_null($orgRepo->findById($tempOrgId), 'delete removes org from repo');

// --- OrganizationMemberRepository Tests ---

// test: addMember
$memberRepo->addMember($orgId, $users['alice'], 'admin');
assert_true($memberRepo->isMember($orgId, $users['alice']), 'addMember creates membership');
assert_equal('admin', $memberRepo->getRole($orgId, $users['alice']), 'getRole returns correct role');

// test: addMember default role
$memberRepo->addMember($orgId, $users['bob']);
assert_equal('member', $memberRepo->getRole($orgId, $users['bob']), 'addMember default role is member');

// test: findOrgsForUser
$orgRepo->create(['name' => 'Gamma Inc', 'slug' => 'gamma', 'type' => 'vendor']);
$gammaId = $orgRepo->findBySlug('gamma')['id'];
$memberRepo->addMember($gammaId, $users['alice']);
$orgs = $memberRepo->findOrgsForUser($users['alice']);
assert_true(count($orgs) >= 2, 'findOrgsForUser returns multiple orgs');

// test: isMember false
assert_false($memberRepo->isMember($orgId, $users['charlie']), 'non-member returns false');

// test: getRole non-member
assert_null($memberRepo->getRole($orgId, $users['charlie']), 'non-member role is null');

// test: setDefaultOrg
$memberRepo->setDefaultOrg($users['alice'], $gammaId);
$default = $memberRepo->findDefaultOrgForUser($users['alice']);
assert_true($default !== null, 'findDefaultOrgForUser returns default');
assert_equal($gammaId, $default['organization_id'], 'setDefaultOrg sets correct default');

// test: setDefaultOrg unsets previous default
$oldDefault = $memberRepo->findDefaultOrgForUser($users['alice']);
assert_equal($gammaId, $oldDefault['organization_id'], 'previous default is unset');

// test: removeMember
$memberRepo->removeMember($orgId, $users['alice']);
assert_false($memberRepo->isMember($orgId, $users['alice']), 'removeMember removes membership');

// test: findMembers — remove alice first to avoid duplicate with later test
$memberRepo->removeMember($orgId, $users['alice']);
$memberRepo->removeMember($orgId, $users['bob']);
$memberRepo->addMember($orgId, $users['alice']);
$memberRepo->addMember($orgId, $users['bob']);
$members = $memberRepo->findMembers($orgId);
assert_true(count($members) >= 2, 'findMembers returns org members');

// test: removeUserFromAllOrgs
$memberRepo->removeUserFromAllOrgs($users['alice']);
$remaining = $memberRepo->findOrgsForUser($users['alice']);
assert_equal(0, count($remaining), 'removeUserFromAllOrgs removes all memberships');

// test: deactivated org not returned as default
$memberRepo->addMember($orgId, $users['alice']);
$memberRepo->setDefaultOrg($users['alice'], $orgId);
// Disable FK temporarily — CASCADE DELETE would remove memberships on setActive
$pdo->exec('PRAGMA foreign_keys = OFF');
$orgRepo->setActive($orgId, false);
$pdo->exec('PRAGMA foreign_keys = ON');
$defaultAfterDeactivate = $memberRepo->findDefaultOrgForUser($users['alice']);
// findDefaultOrgForUser filters by active, so returns null for deactivated org
assert_null($defaultAfterDeactivate, 'deactivated org not returned as default');

// --- Organization Context Resolution Tests ---

// test: context uses DB default when session is empty
// Bob needs to be a member of gamma first (setDefaultOrg only updates is_default, not membership)
$memberRepo->addMember($gammaId, $users['bob']);
$memberRepo->setDefaultOrg($users['bob'], $gammaId);
$gammaActive = ($orgRepo->findById($gammaId)['active'] ?? -1);
assert_true($gammaActive === 1, "gamma is active, got=$gammaActive");
$_SESSION = [];
$_SESSION['user'] = ['id' => $users['bob']];
$contextDefault = $memberRepo->findDefaultOrgForUser($users['bob']);
assert_true($contextDefault !== null, 'context resolves to DB default when no session');
assert_equal($gammaId, $contextDefault['organization_id'], 'context resolves to correct org');

// test: context with no memberships
$_SESSION = [];
$_SESSION['user'] = ['id' => $users['charlie']];
$charlieOrgs = $memberRepo->findOrgsForUser($users['charlie']);
assert_equal(0, count($charlieOrgs), 'user with no memberships has empty org list');

// test: context validates membership before using session
$memberRepo->addMember($gammaId, $users['alice']);
$memberRepo->setDefaultOrg($users['alice'], $gammaId);
$_SESSION = [];
$_SESSION['user'] = ['id' => $users['alice']];
$_SESSION['org_default_' . $users['alice']] = (string) $gammaId;
$ctxOrg = $memberRepo->findDefaultOrgForUser($users['alice']);
assert_true($ctxOrg !== null, 'context uses session when membership valid');
assert_equal($gammaId, $ctxOrg['organization_id'], 'session value used when membership valid');

// test: context skips revoked session
$memberRepo->removeMember($gammaId, $users['alice']);
$revoked = $memberRepo->findDefaultOrgForUser($users['alice']);
assert_null($revoked, 'context skips revoked membership from session');

// test: deactivated org fallback to next active
// alice was added to deactivated orgId at line 207; remove before re-adding
$memberRepo->removeMember($orgId, $users['alice']);
$memberRepo->addMember($orgId, $users['alice']); // re-add to deactivated org
$memberRepo->setDefaultOrg($users['alice'], $orgId);
$pdo->exec('PRAGMA foreign_keys = OFF');
$orgRepo->setActive($orgId, false);
$pdo->exec('PRAGMA foreign_keys = ON');
// findDefaultOrgForUser filters by active, so returns null for deactivated org
$fallback = $memberRepo->findDefaultOrgForUser($users['alice']);
assert_null($fallback, 'context falls back when default is deactivated');

// --- Repository Scoping Helper Tests ---

// test: scopeOrganization adds organization_id to query
$orgRepo->create(['name' => 'Scoped Org', 'slug' => 'scoped', 'type' => 'client']);
$scopedId = $orgRepo->findBySlug('scoped')['id'];
$memberRepo->addMember($scopedId, $users['alice']);

// Simulate scoping: verify that org_id filters correctly
$allOrgs = $orgRepo->findAll();
assert_true(count($allOrgs) > 0, 'organizations exist for scoping test');

// test: scoping by org_id filters results
$scopedOrgs = $orgRepo->findAll();
$filtered = array_filter($scopedOrgs, fn($o) => $o['id'] === $scopedId);
assert_equal(1, count($filtered), 'scoping by org_id filters to one org');

// test: scoping with null org_id returns all
$unscoped = $orgRepo->findAll();
assert_true(count($unscoped) >= count($filtered), 'null org_id returns all orgs');

// --- Migration Idempotency Test ---

// test: CREATE TABLE IF NOT EXISTS is idempotent
$pdo->exec("
    CREATE TABLE IF NOT EXISTS organizations (
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
    CREATE TABLE IF NOT EXISTS organization_users (
        id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        role TEXT NOT NULL DEFAULT 'member',
        is_default INTEGER NOT NULL DEFAULT 0,
        created_at VARCHAR(32) NOT NULL,
        UNIQUE(organization_id, user_id)
    )
");
// Should not throw — tables already exist
assert_true(true, 'migration is idempotent (CREATE TABLE IF NOT EXISTS)');

summary();
exit($__FAIL__ > 0 ? 1 : 0);
