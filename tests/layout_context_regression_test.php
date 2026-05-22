<?php
/**
 * Regression tests: layout context resolution without $this.
 *
 * Proves that:
 * - blank layout context works without $this (public / route)
 * - panel-style context works with controller $this
 * - no undefined array key warnings under E_ALL
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Container;
use App\Core\ViewGlobals;

// ── Helper: mock AuthService ──────────────────────────────────────
class MockAuthServiceForLayoutTest
{
    public function user(): ?array {
        return [
            'id' => 42,
            'username' => 'testuser',
            'display_name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
            'created_at' => '',
            'updated_at' => '',
        ];
    }
}

// ── TEST 1: Empty scope (simulates blank layout without $this) ─────
echo "= TEST 1: Empty scope (no \$this) — no warnings under E_ALL =\n";
error_reporting(E_ALL);
$errBefore = error_get_last();
$scope1 = []; // Simulates: blank layout with no controller context
$result1 = ViewGlobals::contextFromScope($scope1);
$errAfter = error_get_last();

assert_true($errAfter === null, 'no errors with empty scope');
assert_equal([], $result1['currentUserPermissions'], 'permissions empty for guest');
assert_equal('Kernel-Web', $result1['appName'], 'default appName when no config');
echo "PASS\n";

// ── TEST 2: Scope with only layout-level vars (no $this, no container) ─
echo "= TEST 2: Scope with layout vars only (no \$this) =\n";
$scope2 = [
    'pageTitle' => 'Home',
    'appName'   => 'MyApp',
    'content'   => '<p>Hello</p>',
];
$result2 = ViewGlobals::contextFromScope($scope2);
assert_equal('MyApp', $result2['appName'], 'appName from scope');
assert_equal([], $result2['currentUserPermissions'], 'guest permissions');
assert_true(isset($result2['Config']), 'Config still set');
assert_true(isset($result2['config']), 'config still set');
echo "PASS\n";

// ── TEST 3: Scope with $this as Container directly ────────────────
echo "= TEST 3: Scope with \$this as Container =\n";
$container3 = new Container();
$container3->set('auth', new MockAuthServiceForLayoutTest());
$container3->set('config', ['name' => 'ControllerApp', 'app' => ['developer' => true, 'debug' => true]]);
$scope3 = [
    'this'      => $container3,
    'pageTitle' => 'Admin',
];
$result3 = ViewGlobals::contextFromScope($scope3);
assert_equal('ControllerApp', $result3['appName'], 'appName from container');
assert_equal(42, $result3['currentUserId'], 'userId from AuthService');
assert_equal('testuser', $result3['currentUsername'], 'username from AuthService');
assert_equal(['developer' => true, 'debug' => true], $result3['appConfig'], 'appConfig from container');
echo "PASS\n";

// ── TEST 4: Scope with $this as Controller (has ->container prop) ──
echo "= TEST 4: Scope with \$this as Controller (->container) =\n";
$container4 = new Container();
$container4->set('auth', new MockAuthServiceForLayoutTest());
$container4->set('config', ['name' => 'PanelApp', 'app' => ['developer' => false]]);
class MockControllerForTest
{
    public $container;
    public function __construct() { $this->container = null; }
}
$mockCtrl4 = new MockControllerForTest();
$mockCtrl4->container = $container4;
$scope4 = [
    'this'      => $mockCtrl4,
    'principal' => [
        'user' => ['id' => 55, 'username' => 'paneluser', 'display_name' => 'Panel', 'email' => 'p@t.com', 'is_active' => true, 'created_at' => '', 'updated_at' => ''],
        'permissions' => ['admin', 'users.view'],
    ],
    'pageTitle' => 'Permissions',
];
$result4 = ViewGlobals::contextFromScope($scope4);
assert_equal(55, $result4['currentUserId'], 'userId from principal (overrides auth)');
assert_equal('admin', $result4['currentUserPermissions'][0] ?? '', 'permissions from principal');
assert_true($result4['currentUserIsAdmin'], 'isAdmin from principal');
echo "PASS\n";

// ── TEST 5: Scope with global $container ───────────────────────
echo "= TEST 5: Scope with global \$container =\n";
$container5 = new Container();
$container5->set('auth', new MockAuthServiceForLayoutTest());
$container5->set('config', ['name' => 'GlobalApp', 'app' => ['debug' => true]]);
$GLOBALS['container'] = $container5;
$scope5 = []; // No $this, no container key
$result5 = ViewGlobals::contextFromScope($scope5);
assert_equal('GlobalApp', $result5['appName'], 'appName from $GLOBALS');
assert_equal(42, $result5['currentUserId'], 'userId from global auth');
unset($GLOBALS['container']);
echo "PASS\n";

// ── TEST 6: Scope with container key directly ────────────────────
echo "= TEST 6: Scope with direct 'container' key =\n";
$container6 = new Container();
$container6->set('auth', new MockAuthServiceForLayoutTest());
$container6->set('config', ['name' => 'DirectApp', 'app' => ['debug' => false]]);
$scope6 = [
    'container' => $container6,
    'pageTitle' => 'Test',
];
$result6 = ViewGlobals::contextFromScope($scope6);
assert_equal('DirectApp', $result6['appName'], 'appName from container key');
echo "PASS\n";

// ── TEST 7: E_ALL warning check with real-world scope ────────────
echo "= TEST 7: E_ALL — real-world scope (panel layout pattern) =\n";
error_reporting(E_ALL);
$errBefore7 = error_get_last();

$container7 = new Container();
$container7->set('auth', new MockAuthServiceForLayoutTest());
$container7->set('config', ['name' => 'Kernel-Web', 'app' => ['developer' => true, 'debug' => true]]);

class MockCtrl7
{
    public $container;
    public function __construct() { $this->container = $GLOBALS['_ctrl7c']; }
}
$GLOBALS['_ctrl7c'] = $container7;
$ctrl7 = new MockCtrl7();

$scope7 = [
    'this'          => $ctrl7,
    'viewsPath'     => '/fake/views',
    'appName'       => 'Kernel-Web',
    'displayName'   => 'Admin User',
    'permissions'   => ['admin', 'admin.access'],
    'pageTitle'     => 'Permissions',
    'activeSection' => 'Admin Permissions',
    'breadcrumbs'   => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Permissions', 'url' => null]],
    'flash'         => null,
];
$result7 = ViewGlobals::contextFromScope($scope7);
$errAfter7 = error_get_last();

assert_true($errAfter7 === null, 'no E_ALL warnings with panel-style scope');
assert_equal(42, $result7['currentUserId'], 'userId resolved');
assert_equal('admin', $result7['currentUserPermissions'][0] ?? '', 'permissions resolved');
assert_true($result7['currentUserIsAdmin'], 'isAdmin resolved');
echo "PASS\n";

// ── TEST 8: Guest (no auth, no principal, no config) ────────────
echo "= TEST 8: Guest — no auth, no principal, no config =\n";
$guestContainer = new Container();
$guestContainer->set('config', ['name' => 'GuestApp']);
class MockCtrl8
{
    public $container;
    public function __construct() { $this->container = $GLOBALS['_ctrl8c']; }
}
$GLOBALS['_ctrl8c'] = $guestContainer;
$ctrl8 = new MockCtrl8();

$scope8 = [
    'this'      => $ctrl8,
    'pageTitle' => 'Home',
];
$result8 = ViewGlobals::contextFromScope($scope8);

assert_null($result8['currentUser'], 'currentUser null for guest');
assert_equal(0, $result8['currentUserId'], 'userId 0 for guest');
assert_equal([], $result8['currentUserPermissions'], 'permissions empty for guest');
assert_false($result8['currentUserIsAdmin'], 'isAdmin false for guest');
assert_equal('GuestApp', $result8['appName'], 'appName still resolved');
echo "PASS\n";

echo "\n=== ALL LAYOUT CONTEXT REGRESSION TESTS PASSED ===\n";
