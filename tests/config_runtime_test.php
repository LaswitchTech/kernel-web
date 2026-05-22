<?php
/**
 * Runtime integration test: config/app.php loaded via Config::load('app')
 * flows through ViewGlobals::contextFromContainer() to $appConfig correctly.
 *
 * Simulates the exact path used by public/index.php + layouts:
 *   Config::load('app') → container->set('config', ...) →
 *   ViewGlobals::contextFromContainer() → $appConfig['developer']
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Container;
use App\Core\ViewGlobals;

// Simulate what Config::load('app') returns for the real app (flat array)
// This mirrors config/app.php after Env::load('.env') loads:
//   APP_DEVELOPER=true, APP_DEBUG=true, APP_DEV_CONSOLE=true
$flatAppConfig = [
    'name' => 'Kernel-Web',
    'version' => 'dev',
    'env' => 'development',
    'debug' => true,
    'url' => 'http://localhost',
    'installed' => true,
    'developer' => true,
    'dev_console' => true,
];

// ── TEST 1: Container stores flat config → contextFromContainer resolves appConfig
echo "= TEST 1: Flat config through ViewGlobals (real app path) =\n";
error_reporting(E_ALL);
$errBefore = error_get_last();

$container1 = new Container();
$container1->set('config', $flatAppConfig);

$scope1 = ['this' => $container1, 'pageTitle' => 'Test'];
$result1 = ViewGlobals::contextFromContainer($container1, $scope1);

$errAfter = error_get_last();
assert_true($errAfter === null, 'no errors under E_ALL');
assert_true($result1['appConfig']['developer'], 'appConfig[developer] = true');
assert_true($result1['appConfig']['debug'], 'appConfig[debug] = true');
assert_true($result1['appConfig']['dev_console'], 'appConfig[dev_console] = true');
echo "PASS\n";

// ── TEST 2: Simulate dev-tools-offcanvas guard logic with resolved $appConfig
echo "= TEST 2: Dev tools guard with resolved appConfig =\n";
$devEnabled   = (bool) ($result1['appConfig']['developer'] ?? false);
$debugEnabled = (bool) ($result1['appConfig']['debug'] ?? false);
$consoleEnabled = (bool) ($result1['appConfig']['dev_console'] ?? false);
$shouldRender = $devEnabled && $debugEnabled && $consoleEnabled;
assert_true($shouldRender, 'all 3 flags true → should render');
echo "PASS\n";

// ── TEST 3: Any flag false → should not render
echo "= TEST 3: Any flag false → should not render =\n";
$scope3 = ['this' => (new Container()), 'pageTitle' => 'Test'];

// developer=false
$container3a = new Container();
$container3a->set('config', $flatAppConfig);
// We need to mutate the config for this test
$flatNoDeveloper = $flatAppConfig;
$flatNoDeveloper['developer'] = false;
$container3a->set('config', $flatNoDeveloper);
$result3a = ViewGlobals::contextFromContainer($container3a, $scope3);
assert_false((bool) ($result3a['appConfig']['developer'] ?? false), 'developer=false');
echo "PASS\n";

// debug=false
$flatNoDebug = $flatAppConfig;
$flatNoDebug['debug'] = false;
$container3b = new Container();
$container3b->set('config', $flatNoDebug);
$result3b = ViewGlobals::contextFromContainer($container3b, $scope3);
assert_false((bool) ($result3b['appConfig']['debug'] ?? false), 'debug=false');
echo "PASS\n";

// dev_console=false
$flatNoConsole = $flatAppConfig;
$flatNoConsole['dev_console'] = false;
$container3c = new Container();
$container3c->set('config', $flatNoConsole);
$result3c = ViewGlobals::contextFromContainer($container3c, $scope3);
assert_false((bool) ($result3c['appConfig']['dev_console'] ?? false), 'dev_console=false');
echo "PASS\n";

// ── TEST 4: Nested config (existing test pattern) still works
echo "= TEST 4: Nested \$config['app'] still works =\n";
$nestedConfig = [
    'name' => 'Test',
    'app' => ['developer' => true, 'debug' => true, 'dev_console' => true],
];
$container4 = new Container();
$container4->set('config', $nestedConfig);
$result4 = ViewGlobals::contextFromContainer($container4, ['this' => $container4, 'pageTitle' => 'Test']);
assert_true($result4['appConfig']['developer'], 'nested config developer=true');
assert_true($result4['appConfig']['debug'], 'nested config debug=true');
assert_true($result4['appConfig']['dev_console'], 'nested config dev_console=true');
assert_true($result4['appName'] === 'Test', 'appName from nested config');
echo "PASS\n";

// ── TEST 5: appName resolves from flat config top level
echo "= TEST 5: appName resolves from flat config =\n";
assert_equal('Kernel-Web', $result1['appName'], 'appName from flat config');
echo "PASS\n";

// ── TEST 6: All 3 false with flat config → dev tools suppressed
echo "= TEST 6: All false with flat config → no render =\n";
$flatAllFalse = $flatAppConfig;
$flatAllFalse['developer'] = false;
$flatAllFalse['debug'] = false;
$flatAllFalse['dev_console'] = false;
$container6 = new Container();
$container6->set('config', $flatAllFalse);
$result6 = ViewGlobals::contextFromContainer($container6, ['this' => $container6, 'pageTitle' => 'Test']);
$devEnabled6   = (bool) ($result6['appConfig']['developer'] ?? false);
$debugEnabled6 = (bool) ($result6['appConfig']['debug'] ?? false);
$consoleEnabled6 = (bool) ($result6['appConfig']['dev_console'] ?? false);
assert_false($devEnabled6 || $debugEnabled6 || $consoleEnabled6, 'all three false');
echo "PASS\n";

echo "\n=== ALL CONFIG RUNTIME TESTS PASSED ===\n";
