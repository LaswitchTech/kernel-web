<?php
/**
 * Test: ViewGlobals resolves user variables from all sources correctly.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\ViewGlobals;

// ===== TEST: varsFromScope with principal =====
echo "= TEST: varsFromScope resolves from principal =\n";
$scope = [
    'principal' => [
        'user' => [
            'id' => 42,
            'username' => 'testuser',
            'display_name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-02',
        ],
        'permissions' => ['admin', 'users.view'],
    ],
];
$result = ViewGlobals::varsFromScope(null, $scope);
assert_not_null($result['currentUser'], 'currentUser is set');
assert_equal(42, $result['currentUserId'], 'currentUserId is 42');
assert_equal('testuser', $result['currentUsername'], 'currentUsername is testuser');
assert_equal('test@test.com', $result['currentUserEmail'], 'currentUserEmail is correct');
assert_equal('Test User', $result['currentUserDisplayName'], 'currentUserDisplayName is Test User');
assert_equal(['admin', 'users.view'], $result['currentUserPermissions'], 'permissions are correct');
assert_true($result['currentUserIsAdmin'], 'currentUserIsAdmin is true');
echo "PASS\n";

// ===== TEST: varsFromScope with legacy $user =====
echo "= TEST: varsFromScope resolves from legacy \$user =\n";
$scope = [
    'user' => [
        'id' => 7,
        'username' => 'legacy',
        'display_name' => '',
        'email' => 'legacy@test.com',
        'is_active' => true,
        'created_at' => '2024-01-01',
        'updated_at' => '2024-01-02',
    ],
    'permissions' => ['chat.use'],
];
$result = ViewGlobals::varsFromScope(null, $scope);
assert_equal(7, $result['currentUserId'], 'currentUserId is 7');
assert_equal('legacy', $result['currentUsername'], 'falls back to username');
assert_equal('', $result['currentUserDisplayName'], 'display_name is empty string');
assert_true(!$result['currentUserIsAdmin'], 'currentUserIsAdmin is false');
echo "PASS\n";

// ===== TEST: varsFromScope with displayName fallback chain =====
echo "= TEST: currentUserDisplayName fallback chain =\n";
// No display_name, no name → use username
$scope = ['user' => ['id' => 1, 'username' => 'nobody', 'display_name' => '', 'email' => 'nobody@test.com']];
$result = ViewGlobals::varsFromScope(null, $scope);
assert_equal('nobody', $result['currentUserDisplayName'], 'falls back to username');

// No display_name, no username → use email
$scope = ['user' => ['id' => 1, 'username' => '', 'display_name' => '', 'email' => 'email@test.com']];
$result = ViewGlobals::varsFromScope(null, $scope);
assert_equal('email@test.com', $result['currentUserDisplayName'], 'falls back to email');
echo "PASS\n";

// ===== TEST: varsFromScope with permissions from principal only =====
echo "= TEST: permissions resolved from principal when \$permissions missing =\n";
$scope = ['principal' => ['user' => ['id' => 1, 'username' => 'u', 'display_name' => 'U', 'email' => 'u@t.com'], 'permissions' => ['admin.access', 'foo.bar']]];
$result = ViewGlobals::varsFromScope(null, $scope);
assert_equal(['admin.access', 'foo.bar'], $result['currentUserPermissions'], 'permissions from principal');
assert_true($result['currentUserIsAdmin'], 'isAdmin true for admin.access');
echo "PASS\n";

// ===== TEST: varsFromScope guest defaults =====
echo "= TEST: varsFromScope guest defaults =\n";
$scope = [];
$result = ViewGlobals::varsFromScope(null, $scope);
assert_null($result['currentUser'], 'currentUser is null');
assert_equal(0, $result['currentUserId'], 'currentUserId is 0');
assert_equal('', $result['currentUsername'], 'currentUsername is empty');
assert_equal('', $result['currentUserEmail'], 'currentUserEmail is empty');
assert_equal('', $result['currentUserDisplayName'], 'currentUserDisplayName is empty');
assert_equal([], $result['currentUserPermissions'], 'permissions is empty array');
assert_false($result['currentUserIsAdmin'], 'isAdmin is false');
echo "PASS\n";

// ===== TEST: varsFromScope with no principal, no user, no permissions =====
echo "= TEST: varsFromScope with no user data =\n";
$scope = ['pageTitle' => 'Test', 'appName' => 'Test App'];
$result = ViewGlobals::varsFromScope(null, $scope);
assert_null($result['currentUser'], 'currentUser is null with no user data');
assert_equal(0, $result['currentUserId'], 'currentUserId is 0');
echo "PASS\n";

// ===== TEST: vars with AuthService-like object =====
echo "= TEST: vars resolves from AuthService fallback =\n";
class MockAuthServiceForTest
{
    public function user(): ?array { return ['id' => 99, 'username' => 'mock', 'display_name' => '', 'email' => 'mock@test.com', 'is_active' => true, 'created_at' => '', 'updated_at' => '']; }
}
$mockAuth = new MockAuthServiceForTest();
$result = ViewGlobals::vars($mockAuth);
assert_not_null($result['currentUser'], 'currentUser from auth fallback');
assert_equal(99, $result['currentUserId'], 'currentUserId from auth fallback');
echo "PASS\n";

// ===== TEST: vars with explicit user array =====
echo "= TEST: vars resolves explicit user array =\n";
$explicitUser = ['id' => 5, 'username' => 'explicit', 'display_name' => 'Explicit', 'email' => 'e@t.com', 'is_active' => true, 'created_at' => '', 'updated_at' => ''];
$result = ViewGlobals::vars(null, $explicitUser);
assert_equal(5, $result['currentUserId'], 'currentUserId is 5');
assert_equal('Explicit', $result['currentUserDisplayName'], 'display_name is Explicit');
echo "PASS\n";

echo "\n=== ALL TESTS PASSED ===\n";
