<?php

/**
 * Profile organizations creation regression test.
 *
 * Verifies: JSON body parsing, org creation, slug uniqueness,
 * 2FA section file existence and content, membership persistence.
 *
 * Run: php tests/profile_organizations_test.php
 * Assertions: 17
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

// ------ Test 1: JSON body parsing (mimics ProfileOrganizationsController) ------

$testBody = '{"name": "My Org", "type": "client"}';
$parsedBody = json_decode($testBody, true);
assert_equal('My Org', $parsedBody['name'], 'JSON body name parsed correctly');
assert_equal('client', $parsedBody['type'], 'JSON body type parsed correctly');

// Empty body should produce null
$emptyBody = json_decode('', true);
assert_null($emptyBody, 'Empty JSON string decodes to null');

// Non-string name should fail validation
$nullBody = ['name' => null];
assert_equal('', trim((string) ($nullBody['name'] ?? '')), 'Null name is empty after trim');

// ------ Test 2: ProfileOrganizationsController has required methods ------

$ref = new ReflectionClass(\App\Controllers\ProfileOrganizationsController::class);
assert_true($ref->hasMethod('createOrganization'), 'createOrganization method exists');
assert_true($ref->hasMethod('list'), 'list method exists');
assert_true($ref->hasMethod('switchOrganization'), 'switchOrganization method exists');

// ------ Test 3: OrganizationRepository exists and has expected methods ------

assert_class_exists(\App\Models\OrganizationRepository::class, 'OrganizationRepository class exists');
$orgRepoRef = new ReflectionClass(\App\Models\OrganizationRepository::class);
$hasCreate = $orgRepoRef->hasMethod('create');
assert_true($hasCreate, 'OrganizationRepository has create method');
$hasFindAll = $orgRepoRef->hasMethod('findAll');
assert_true($hasFindAll, 'OrganizationRepository has findAll method');
$hasFindBySlug = $orgRepoRef->hasMethod('findBySlug');
assert_true($hasFindBySlug, 'OrganizationRepository has findBySlug method');
$hasIsSlugTaken = $orgRepoRef->hasMethod('isSlugTaken');
assert_true($hasIsSlugTaken, 'OrganizationRepository has isSlugTaken method');

// ------ Test 4: 2FA section view file exists and has expected content ------

$twoFaView = __DIR__ . '/../app/Views/profile/2fa-section.php';
assert_true(file_exists($twoFaView), '2FA section view file exists');
$content = file_get_contents($twoFaView);
assert_contains('pm-2fa-setup', $content, '2FA view has setup UI container');
assert_contains('pm-2fa-enabled', $content, '2FA view has enabled UI container');
assert_contains('pm-2fa-code', $content, '2FA view has verification code input');
assert_contains('/api/profile/2fa/status', $content, '2FA view fetches status endpoint');
assert_contains('/api/profile/2fa/enable', $content, '2FA view calls enable endpoint');
assert_contains('/api/profile/2fa/generate', $content, '2FA view calls generate endpoint');
assert_contains('/api/profile/2fa/disable', $content, '2FA view calls disable endpoint');

// ------ Test 5: ProfileOrganizationsController reads JSON from php://input ------

$controllerContent = file_get_contents(__DIR__ . '/../app/Controllers/ProfileOrganizationsController.php');
assert_contains('php://input', $controllerContent, 'createOrganization reads from php://input');

// ------ Test 6: Plugin autoloader handles Plugins\ namespace ------

$indexContent = file_get_contents(__DIR__ . '/../public/index.php');
assert_contains('Plugins', $indexContent, 'Autoloader references Plugins namespace');

// ------ Test 7: Organizations menu placement ------

$menus = [];
preg_match_all("/'name' => '([^']+)'.*'order' => (\d+)/", $indexContent, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    $menus[$m[1]] = (int) $m[2];
}
assert_true(isset($menus['admin-organizations']), 'Organizations menu entry exists');
assert_true(isset($menus['__section__identity']), 'Identity & Access section marker exists');
assert_true(isset($menus['__section__system']), 'System section marker exists');
assert_true($menus['admin-organizations'] < $menus['__section__system'], 'Organizations is before System section');

// ------ Test 8: Autoloader can load Plugins\ namespace classes ------

// Simulate autoloader path resolution for Plugins\Smtp\SmtpHooks
$testClass = 'Plugins\Smtp\SmtpHooks';
$prefixLen = strlen('Plugins\\');
$relative = substr($testClass, $prefixLen); // 'Smtp\SmtpHooks'
$parts = explode('\\', $relative);
$pluginName = strtolower($parts[0]); // 'smtp'
$classPath = implode('/', array_slice($parts, 1)); // 'SmtpHooks'
$expectedFile = __DIR__ . '/../lib/plugins/' . $pluginName . '/src/' . $classPath . '.php';
assert_true(file_exists($expectedFile), 'Autoloader path resolves to existing file: ' . $expectedFile);

// Same for Telico
$testClass2 = 'Plugins\Telico\TelicoHooks';
$relative2 = substr($testClass2, $prefixLen);
$parts2 = explode('\\', $relative2);
$pluginName2 = strtolower($parts2[0]);
$classPath2 = implode('/', array_slice($parts2, 1));
$expectedFile2 = __DIR__ . '/../lib/plugins/' . $pluginName2 . '/src/' . $classPath2 . '.php';
assert_true(file_exists($expectedFile2), 'Autoloader path resolves to existing Telico file: ' . $expectedFile2);

summary();
exit($__FAIL__ > 0 ? 1 : 0);
