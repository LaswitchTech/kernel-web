<?php

/**
 * CRUD tests for core auth entities: users, groups, permissions, tokens.
 *
 * Uses an in-memory SQLite database — no production data touched.
 * Follows the same zero-dependency pattern as migration_test.php.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Models\UserRepository;
use App\Models\GroupRepository;
use App\Models\PermissionRepository;
use App\Models\TokenRepository;
use App\Auth\TokenService;
use App\Core\Gate;
use App\Core\DatabaseInterface;

// --- Bootstrap: in-memory SQLite + TestDB ---

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

// --- Create auth tables (mirror production migrations) ---

$db->execute("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    display_name TEXT DEFAULT '',
    password_hash TEXT NOT NULL,
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)");

$db->execute("CREATE TABLE groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    description TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)");

$db->execute("CREATE TABLE permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    description TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)");

$db->execute("CREATE TABLE user_groups (
    user_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, group_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (group_id) REFERENCES groups(id)
)");

$db->execute("CREATE TABLE group_permissions (
    group_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    PRIMARY KEY (group_id, permission_id),
    FOREIGN KEY (group_id) REFERENCES groups(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
)");

$db->execute("CREATE TABLE api_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    token_hash TEXT UNIQUE NOT NULL,
    last_used_at TEXT,
    expires_at TEXT,
    revoked_at TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

// Index on user_id for findByUserId performance — mirrors production migration
$db->execute("CREATE INDEX api_tokens_user_id ON api_tokens (user_id)");

// --- Instantiate repositories ---

$userRepo = new UserRepository($db);
$groupRepo = new GroupRepository($db);
$permRepo = new PermissionRepository($db);
$tokenRepo = new TokenRepository($db);
$gate = new Gate($db);
$tokenService = new TokenService($tokenRepo, $userRepo, $gate);

// --- Constructor sanity check: all repos accept DatabaseInterface ---
assert_instance_of($userRepo, 'App\Models\UserRepository', 'UserRepository accepts DatabaseInterface');
assert_instance_of($groupRepo, 'App\Models\GroupRepository', 'GroupRepository accepts DatabaseInterface');
assert_instance_of($permRepo, 'App\Models\PermissionRepository', 'PermissionRepository accepts DatabaseInterface');
assert_instance_of($tokenRepo, 'App\Models\TokenRepository', 'TokenRepository accepts DatabaseInterface');
assert_instance_of($gate, 'App\Core\Gate', 'Gate accepts DatabaseInterface');
assert_instance_of($tokenService, 'App\Auth\TokenService', 'TokenService accepts TokenRepository + UserRepository + Gate');

// ================================================
// USERS
// ================================================

// --- User creation ---
$newId = $userRepo->create([
    'display_name'  => 'Test User',
    'username'      => 'testuser',
    'email'         => 'test@example.com',
    'password_hash' => password_hash('password', PASSWORD_DEFAULT),
]);
assert_not_null($newId, 'create returns non-null ID');
assert_true($newId > 0, 'create returns positive ID');

// --- Find by ID ---
$user = $userRepo->findById($newId);
assert_not_null($user, 'findById returns user');
assert_equal('testuser', $user['username'], 'username matches');
assert_equal('Test User', $user['display_name'], 'display_name matches');
assert_equal('test@example.com', $user['email'], 'email matches');

// --- findByIdAny returns inactive user ---
$inactiveId = $userRepo->create([
    'display_name'  => 'Inactive User',
    'username'      => 'inactive_user',
    'email'         => 'inactive@example.com',
    'password_hash' => password_hash('password', PASSWORD_DEFAULT),
    'is_active'     => 0,
]);
$anyUser = $userRepo->findByIdAny($inactiveId);
assert_not_null($anyUser, 'findByIdAny finds inactive user');
assert_equal('inactive_user', $anyUser['username'], 'findByIdAny username matches');

// --- findById ignores inactive ---
$inactiveById = $userRepo->findById($inactiveId);
assert_null($inactiveById, 'findById ignores inactive user');

// --- Find by username ---
$foundByUsername = $userRepo->findByUsername('testuser');
assert_not_null($foundByUsername, 'findByUsername finds active user');
assert_equal($newId, $foundByUsername['id'], 'findByUsername returns correct user');

// --- findByEmail ---
$foundByEmail = $userRepo->findByEmail('test@example.com');
assert_not_null($foundByEmail, 'findByEmail finds active user');
assert_equal($newId, $foundByEmail['id'], 'findByEmail returns correct user');

// --- isUsernameTaken ---
assert_true($userRepo->isUsernameTaken('testuser'), 'isUsernameTaken returns true for taken name');
assert_true($userRepo->isUsernameTaken('inactive_user'), 'isUsernameTaken finds inactive user');
assert_false($userRepo->isUsernameTaken('nonexistent'), 'isUsernameTaken returns false for free name');
assert_false($userRepo->isUsernameTaken('testuser', $newId), 'isUsernameTaken with excludeId ignores self');

// --- isEmailTaken ---
assert_true($userRepo->isEmailTaken('test@example.com'), 'isEmailTaken returns true for taken email');
assert_false($userRepo->isEmailTaken('nobody@example.com'), 'isEmailTaken returns false for free email');

// --- Update user ---
$userRepo->update($newId, ['display_name' => 'Updated Name', 'email' => 'updated@example.com']);
$updated = $userRepo->findById($newId);
assert_equal('Updated Name', $updated['display_name'], 'update changes display_name');
assert_equal('updated@example.com', $updated['email'], 'update changes email');

// --- Update password ---
$newHash = password_hash('newpass', PASSWORD_DEFAULT);
$userRepo->setPassword($newId, $newHash);
$userRecheck = $userRepo->findById($newId);
assert_true(password_verify('newpass', $userRecheck['password_hash']), 'setPassword updates hash correctly');

// --- Find non-existent user ---
assert_null($userRepo->findById(99999), 'findById returns null for missing ID');
assert_null($userRepo->findByUsername('nonexistent'), 'findByUsername returns null for missing user');
assert_null($userRepo->findByEmail('nonexistent@example.com'), 'findByEmail returns null for missing email');

// --- setActive ---
$userRepo->setActive($newId, false);
assert_null($userRepo->findById($newId), 'findById ignores user after setActive(0)');
assert_not_null($userRepo->findByIdAny($newId), 'findByIdAny still finds user');
$userRepo->setActive($newId, true);
assert_not_null($userRepo->findById($newId), 'findById finds user after setActive(1)');

// --- findAllActive ---
$allActive = $userRepo->findAllActive();
assert_true(count($allActive) >= 1, 'findAllActive returns active users');
$usernames = array_column($allActive, 'username');
assert_contains('testuser', $usernames, 'findAllActive includes testuser');

// --- findAll ---
$allUsers = $userRepo->findAll();
assert_true(count($allUsers) >= 2, 'findAll returns all users');
$usernames = array_column($allUsers, 'username');
assert_contains('testuser', $usernames, 'findAll includes testuser');
assert_contains('inactive_user', $usernames, 'findAll includes inactive_user');

// ================================================
// GROUPS
// ================================================

// --- Group creation ---
$groupId = $groupRepo->create(['name' => 'test_group', 'description' => 'A test group']);
assert_true($groupId > 0, 'create returns positive ID');

$group = $groupRepo->findById($groupId);
assert_not_null($group, 'findById returns group');
assert_equal('test_group', $group['name'], 'group name matches');
assert_equal('A test group', $group['description'], 'group description matches');

// --- isNameTaken ---
assert_true($groupRepo->isNameTaken('test_group'), 'isNameTaken for existing group');
assert_false($groupRepo->isNameTaken('other_group'), 'isNameTaken for non-existing group');
assert_false($groupRepo->isNameTaken('test_group', $groupId), 'isNameTaken with excludeId ignores self');

// --- findAll ---
$groupRepo->create(['name' => 'second_group', 'description' => '']);
$allGroups = $groupRepo->findAll();
assert_true(count($allGroups) >= 2, 'findAll returns multiple groups');

// --- update ---
$groupRepo->update($groupId, ['name' => 'renamed_group', 'description' => 'Updated']);
$renamed = $groupRepo->findById($groupId);
assert_equal('renamed_group', $renamed['name'], 'update changes name');
assert_equal('Updated', $renamed['description'], 'update changes description');

// --- isSystemGroup ---
assert_true($groupRepo->isSystemGroup('admin'), 'admin is a system group');
assert_false($groupRepo->isSystemGroup('test_group'), 'test_group is not a system group');
assert_true($groupRepo->isSystemGroup('ADMIN'), 'case-insensitive check');

// --- create group with empty description ---
$noDescId = $groupRepo->create(['name' => 'no_desc_group', 'description' => '']);
$noDesc = $groupRepo->findById($noDescId);
assert_equal(null, $noDesc['description'], 'empty description stored as null');

// --- delete group ---
$groupRepo->delete($noDescId);
assert_null($groupRepo->findById($noDescId), 'findById returns null after delete');

// ================================================
// PERMISSIONS
// ================================================

// --- Permission creation ---
$permId = $permRepo->create(['name' => 'test.perm', 'description' => 'Test permission']);
assert_true($permId > 0, 'permission create returns positive ID');

$perm = $permRepo->findById($permId);
assert_not_null($perm, 'findById returns permission');
assert_equal('test.perm', $perm['name'], 'permission name matches');

// --- findAll ---
$permRepo->create(['name' => 'another.perm', 'description' => '']);
$allPerms = $permRepo->findAll();
assert_true(count($allPerms) >= 2, 'findAll returns multiple permissions');

// --- isCodeTaken ---
assert_true($permRepo->isCodeTaken('test.perm'), 'isCodeTaken for existing permission');
assert_false($permRepo->isCodeTaken('nonexistent_perm'), 'isCodeTaken for non-existing permission');
assert_false($permRepo->isCodeTaken('test.perm', $permId), 'isCodeTaken with excludeId ignores self');

// --- isInUse ---
assert_false($permRepo->isInUse($permId), 'isInUse returns false when not assigned');

// --- update ---
$permRepo->update($permId, ['name' => 'renamed.perm', 'description' => 'Updated']);
$renamedPerm = $permRepo->findById($permId);
assert_equal('renamed.perm', $renamedPerm['name'], 'update changes permission name');

// --- findAll returns group_count (findById does not) ---
$allPermsCheck = $permRepo->findAll();
$foundInAll = false;
foreach ($allPermsCheck as $p) {
    if ((int) $p['id'] === $permId) {
        $foundInAll = true;
        assert_true(isset($p['group_count']), 'findAll rows include group_count');
        assert_equal(0, (int) $p['group_count'], 'group_count is 0 before assignment');
        break;
    }
}
assert_true($foundInAll, 'found permission in findAll result');

// --- delete ---
$permRepo->create(['name' => 'deleteable_perm', 'description' => '']);
$deletePermId = $permRepo->create(['name' => 'delete_perm2', 'description' => '']);
$permRepo->delete($deletePermId);
assert_null($permRepo->findById($deletePermId), 'findById returns null after delete');

// ================================================
// GROUP -> USER MEMBERSHIP
// ================================================

// --- Assign user to group ---
$userRepo->syncGroups($newId, [$groupId]);
$groups = $userRepo->findGroups($newId);
assert_true(count($groups) >= 1, 'findGroups returns the assigned group');
assert_equal('renamed_group', $groups[0]['name'], 'findGroups group name matches');

// --- Multiple groups ---
$secondGroupId = $groupRepo->create(['name' => 'multi_group', 'description' => '']);
$userRepo->syncGroups($newId, [$groupId, $secondGroupId]);
$groups = $userRepo->findGroups($newId);
assert_equal(2, count($groups), 'findGroups returns two groups');

// --- Remove all groups ---
$userRepo->syncGroups($newId, []);
$groups = $userRepo->findGroups($newId);
assert_equal(0, count($groups), 'findGroups returns empty after sync to []');

// --- findMembers (group perspective) ---
$userRepo->syncGroups($newId, [$groupId]);
$members = $groupRepo->findMembers($groupId);
assert_true(count($members) >= 1, 'findMembers returns users in group');

// --- group permissions ---
$groupRepo->syncPermissions($groupId, [$permId]);
$perms = $groupRepo->findPermissions($groupId);
assert_true(count($perms) >= 1, 'findPermissions returns assigned permissions');
assert_equal('renamed.perm', $perms[0]['name'], 'findPermissions permission name matches');

// --- isInUse after sync ---
assert_true($permRepo->isInUse($permId), 'isInUse returns true after syncPermissions');

// ================================================
// TOKENS (TokenRepository)
// ================================================

// --- Create user for token tests ---
$tokenUserId = $userRepo->create([
    'display_name'  => 'Token User',
    'username'      => 'tokenuser',
    'email'         => 'token@example.com',
    'password_hash' => password_hash('password', PASSWORD_DEFAULT),
]);

// --- Create token (TokenService) ---
$tokenResult = $tokenService->generate($tokenUserId, 'test-token', null);
assert_true(isset($tokenResult['raw']), 'generate returns raw key');
assert_true(isset($tokenResult['record']), 'generate returns record');
assert_true(isset($tokenResult['record']['user_id']), 'record has user_id');
assert_true(isset($tokenResult['record']['name']), 'record has name');
assert_false(isset($tokenResult['record']['token_hash']), 'record does not contain hash');
assert_equal('test-token', $tokenResult['record']['name'], 'token name matches');

// --- findByHash (via TokenService::verify) ---
$principal = $tokenService->verify($tokenResult['raw']);
assert_not_null($principal, 'verify returns principal for valid token');
assert_equal('token', $principal['auth_method'], 'auth_method is token');
assert_not_null($principal['user'], 'principal has user');
assert_not_null($principal['token'], 'principal has token');
assert_true(isset($principal['permissions']), 'principal has permissions');

// --- List tokens ---
$list = $tokenService->listForUser($tokenUserId);
assert_true(count($list) >= 1, 'listForUser returns at least one token');

// --- Verify with wrong token ---
assert_null($tokenService->verify('wrong-token'), 'verify returns null for invalid token');
assert_null($tokenService->verify(''), 'verify returns null for empty token');

// --- Revoke token ---
$revokeTokenId = $tokenResult['record']['id'];
assert_true($tokenService->revoke($revokeTokenId, $tokenUserId), 'revoke returns true');

// --- Verify revoked token ---
$revokedPrincipal = $tokenService->verify($tokenResult['raw']);
assert_null($revokedPrincipal, 'verify returns null for revoked token');

// --- Verify token with different user revokes fails ---
assert_false($tokenService->revoke($revokeTokenId, 99999), 'revoke returns false for wrong user');

// --- Token listed as revoked ---
$tokens = $tokenService->listForUser($tokenUserId);
$foundRevoked = null;
foreach ($tokens as $t) {
    if ((int) $t['id'] === $revokeTokenId) {
        $foundRevoked = $t;
        break;
    }
}
assert_not_null($foundRevoked, 'revoked token still in list');
assert_not_null($foundRevoked['revoked_at'], 'revoked_at is set on revoked token');

// --- Token for non-existent user ---
$emptyList = $tokenService->listForUser(99999);
assert_equal(0, count($emptyList), 'listForUser returns empty for non-existent user');

// --- verify returns null for expired token ---
$expireUserId = $userRepo->create([
    'display_name'  => 'Expiry User',
    'username'      => 'expiryuser',
    'email'         => 'expiry@example.com',
    'password_hash' => password_hash('password', PASSWORD_DEFAULT),
]);
$expToken = $tokenService->generate($expireUserId, 'expiring-token', null);
// Insert a separate token with expires_at in the past (different raw to avoid UNIQUE conflict)
$expRaw = bin2hex(random_bytes(32));
$pastDate = '2020-01-01 00:00:00';
$db->execute(
    'INSERT INTO api_tokens (user_id, name, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
    [$expireUserId, 'expired-token', hash('sha256', $expRaw), $pastDate, date('Y-m-d H:i:s')]
);
assert_null($tokenService->verify($expRaw), 'verify returns null for expired token');

// --- verify returns null when token user is inactive ---
$inactiveUserId = $userRepo->create([
    'display_name'  => 'Deactivated User',
    'username'      => 'deactivated_user',
    'email'         => 'deactivated@example.com',
    'password_hash' => password_hash('password', PASSWORD_DEFAULT),
    'is_active'     => 0,
]);
$deactivatedToken = $tokenService->generate($inactiveUserId, 'inactive-token', null);
assert_null($tokenService->verify($deactivatedToken['raw']), 'verify returns null for token belonging to inactive user');

// ================================================
// GATE (permission checking)
// ================================================

// --- User with group has permissions ---
$gatePerms = $gate->permissionsForUser($newId);
assert_true(count($gatePerms) >= 1, 'permissionsForUser returns permissions via group');
assert_contains('renamed.perm', $gatePerms, 'gate includes group permission');

// --- userCan ---
assert_true($gate->userCan($newId, 'renamed.perm'), 'userCan returns true for granted permission');
assert_false($gate->userCan($newId, 'nonexistent.perm'), 'userCan returns false for ungranted permission');

// --- User without any group ---
$userRepo->syncGroups($newId, []);
$emptyPerms = $gate->permissionsForUser($newId);
assert_equal(0, count($emptyPerms), 'permissionsForUser returns empty for user without groups');

// --- Gate can() ---
$principal2 = ['permissions' => ['admin', 'users.view']];
assert_true($gate->can($principal2, 'admin'), 'can returns true for permission in principal');
assert_false($gate->can($principal2, 'missing.perm'), 'can returns false for missing permission');

// --- Gate can() with missing permissions key ---
$principalNoPerms = ['auth_method' => 'session'];
assert_false($gate->can($principalNoPerms, 'admin'), 'can returns false when permissions key is absent');

// --- Summary ===
summary();
exit($__FAIL__ > 0 ? 1 : 0);
