<?php
/**
 * Test: Global View Context provides all globals from container,
 * regardless of what the controller sets in local scope.
 *
 * This proves /admin/permissions-style scope works even when the controller
 * does not define $config or $auth locally.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Container;
use App\Core\ViewGlobals;

// ── Helper: mock AuthService ──────────────────────────────────────
class MockAuthServiceForGlobalTest
{
    public function user(): ?array {
        return [
            'id' => 42,
            'username' => 'testuser',
            'display_name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-02',
        ];
    }
}

// ── TEST 1: contextFromContainer resolves config from container ─────
echo "= TEST 1: contextFromContainer resolves config from container =\n";
$container = new Container();
$container->set('config', ['name' => 'MyApp', 'app' => ['developer' => true, 'debug' => true]]);
$scope = []; // Controller sets NOTHING — simulates /admin/permissions
$result = ViewGlobals::contextFromContainer($container, $scope);

assert_equal('MyApp', $result['appName'], 'appName from container');
assert_true(isset($result['Config']), 'Config is set');
assert_equal(['name' => 'MyApp', 'app' => ['developer' => true, 'debug' => true]], $result['config'], 'config from container');
assert_equal(['developer' => true, 'debug' => true], $result['appConfig'], 'appConfig derived from config');
echo "PASS\n";

// ── TEST 2: contextFromContainer resolves user from container ───────
echo "= TEST 2: contextFromContainer resolves user from AuthService =\n";
$mockAuth = new MockAuthServiceForGlobalTest();
$container2 = new Container();
$container2->set('auth', $mockAuth);
$container2->set('config', ['name' => 'MyApp', 'app' => []]);
$scope2 = []; // No principal in scope — user comes from AuthService
$result2 = ViewGlobals::contextFromContainer($container2, $scope2);

assert_not_null($result2['currentUser'], 'currentUser from AuthService');
assert_equal(42, $result2['currentUserId'], 'currentUserId from AuthService');
assert_equal('testuser', $result2['currentUsername'], 'currentUsername from AuthService');
assert_equal('test@test.com', $result2['currentUserEmail'], 'currentUserEmail from AuthService');
assert_equal('Test User', $result2['currentUserDisplayName'], 'currentUserDisplayName from AuthService');
assert_equal([], $result2['currentUserPermissions'], 'empty permissions when no principal');
echo "PASS\n";

// ── TEST 3: contextFromContainer resolves principal from scope ──────
echo "= TEST 3: contextFromContainer resolves principal from scope =\n";
$mockAuth3 = new MockAuthServiceForGlobalTest();
$container3 = new Container();
$container3->set('auth', $mockAuth3);
$container3->set('config', ['name' => 'MyApp', 'app' => []]);
$scope3 = [
    'principal' => [
        'user' => [
            'id' => 99,
            'username' => 'scopeuser',
            'display_name' => 'Scope User',
            'email' => 'scope@test.com',
            'is_active' => true,
            'created_at' => '',
            'updated_at' => '',
        ],
        'permissions' => ['admin', 'users.view'],
    ],
];
// principal in scope should override AuthService
$result3 = ViewGlobals::contextFromContainer($container3, $scope3);
assert_equal(99, $result3['currentUserId'], 'currentUserId from scope principal (overrides auth)');
assert_equal('scopeuser', $result3['currentUsername'], 'currentUsername from scope principal');
assert_equal(['admin', 'users.view'], $result3['currentUserPermissions'], 'permissions from scope principal');
assert_true($result3['currentUserIsAdmin'], 'currentUserIsAdmin true for admin permission');
echo "PASS\n";

// ── TEST 4: contextFromContainer with guest (no auth, no principal) ─
echo "= TEST 4: contextFromContainer guest defaults =\n";
$guestContainer = new Container();
$guestContainer->set('config', ['name' => 'GuestApp', 'app' => []]);
// No auth set — guest
$scope4 = [];
$result4 = ViewGlobals::contextFromContainer($guestContainer, $scope4);

assert_null($result4['currentUser'], 'currentUser is null for guest');
assert_equal(0, $result4['currentUserId'], 'currentUserId is 0 for guest');
assert_equal('', $result4['currentUsername'], 'currentUsername is empty for guest');
assert_equal('', $result4['currentUserEmail'], 'currentUserEmail is empty for guest');
assert_equal([], $result4['currentUserPermissions'], 'permissions empty for guest');
assert_false($result4['currentUserIsAdmin'], 'currentUserIsAdmin false for guest');
assert_equal('GuestApp', $result4['appName'], 'appName still resolved for guest');
assert_equal([], $result4['appConfig'], 'appConfig empty for guest');
echo "PASS\n";

// ── TEST 5: contextFromContainer resolves $Config AND $config ───────
echo "= TEST 5: contextFromContainer sets both Config and config =\n";
$container5 = new Container();
$container5->set('config', ['name' => 'Dual', 'app' => ['developer' => true]]);
$scope5 = [];
$result5 = ViewGlobals::contextFromContainer($container5, $scope5);
assert_equal(['name' => 'Dual', 'app' => ['developer' => true]], $result5['Config'], 'Config set');
assert_equal(['name' => 'Dual', 'app' => ['developer' => true]], $result5['config'], 'config set');
echo "PASS\n";

// ── TEST 6: Simulates /admin/permissions scope exactly ──────────────
// PermissionController::index() sets: viewsPath, appName, displayName,
// permissions, pageTitle, activeSection, content, breadcrumbs
// It does NOT set: config, auth, user
echo "= TEST 6: Simulates /admin/permissions scope exactly =\n";
$mockAuth6 = new MockAuthServiceForGlobalTest();
$permContainer = new Container();
$permContainer->set('auth', $mockAuth6);
$permContainer->set('config', [
    'name' => 'Kernel-Web',
    'app' => ['developer' => true, 'debug' => true],
    'debug' => true,
]);
// This is what PermissionController sets in scope (no config, no auth)
$permScope = [
    'viewsPath'   => '/fake/views',
    'appName'     => 'Kernel-Web',
    'displayName' => 'Test User',
    'permissions' => ['admin', 'admin.access', 'users.view'],
    'pageTitle'   => 'Permissions',
    'activeSection' => 'Admin Permissions',
    'flash'       => null,
    'breadcrumbs' => [['label' => 'Administration', 'url' => '/admin'], ['label' => 'Permissions', 'url' => null]],
    // No $config — controller reads it from container, doesn't set it in scope
    // No $auth — container has it
    // No $user — AuthService provides it
];
$permResult = ViewGlobals::contextFromContainer($permContainer, $permScope);

// These are what dev-tools-offcanvas needs
assert_true(isset($permResult['config']), 'config available for dev-tools');
assert_true(isset($permResult['appConfig']), 'appConfig available for dev-tools');
assert_true($permResult['appConfig']['developer'], 'developer mode enabled');
assert_true($permResult['appConfig']['debug'], 'debug mode enabled');

// These are what user-menu needs
assert_equal(42, $permResult['currentUserId'], 'currentUserId available for user-menu');
assert_equal('testuser', $permResult['currentUsername'], 'currentUsername available for user-menu');
assert_equal('test@test.com', $permResult['currentUserEmail'], 'currentUserEmail available for user-menu');
assert_equal('Test User', $permResult['currentUserDisplayName'], 'currentUserDisplayName available for user-menu');
assert_equal(['admin', 'admin.access', 'users.view'], $permResult['currentUserPermissions'], 'currentUserPermissions available for user-menu');
assert_true($permResult['currentUserIsAdmin'], 'currentUserIsAdmin available for user-menu');
echo "PASS\n";

// ── TEST 7: Controller $config overrides container $config ─────────
echo "= TEST 7: Scope config merges with container config =\n";
$mergeContainer = new Container();
$mergeContainer->set('config', ['name' => 'ContainerApp', 'app' => ['debug' => true]]);
$mergeScope = [
    'config' => ['name' => 'OverrideApp', 'app' => ['developer' => true]],
];
$mergeResult = ViewGlobals::contextFromContainer($mergeContainer, $mergeScope);
// array_merge: scope values override container values
assert_equal('OverrideApp', $mergeResult['appName'], 'appName from merged config');
echo "PASS\n";

echo "\n=== ALL GLOBAL CONTEXT TESTS PASSED ===\n";
