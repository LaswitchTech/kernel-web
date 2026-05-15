<?php

/**
 * Tests for VersionProvider — kernel and application version resolution.
 *
 * Uses temporary fixture directories so tests don't depend on the real repo files.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\VersionProvider;

// ===== Helper: create a temp fixture directory =====

function createTempFixture(string $type, string $content): string
{
    $tmpDir = sys_get_temp_dir() . '/version_test_' . uniqid();
    mkdir($tmpDir, 0700, true);

    if ($type === 'version_file') {
        file_put_contents($tmpDir . '/VERSION', $content);
    } elseif ($type === 'composer') {
        file_put_contents($tmpDir . '/composer.json', $content);
    }

    return $tmpDir;
}

function cleanupTempFixture(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $files = scandir($dir);
    if ($files === false) {
        return;
    }
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            array_map(fn($f) => unlink($path . '/' . $f), scandir($path));
            rmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// ===== getKernelVersion: VERSION file =====

$fixture = createTempFixture('version_file', '1.2.3');
$vp = new VersionProvider($fixture);
assert_equal('1.2.3', $vp->getKernelVersion(), 'getKernelVersion reads VERSION file');
cleanupTempFixture($fixture);

// ===== getKernelVersion: composer.json =====

$fixture = createTempFixture('composer', json_encode(['name' => 'kernel-web/kernel', 'version' => '2.0.0']));
$vp = new VersionProvider($fixture);
assert_equal('2.0.0', $vp->getKernelVersion(), 'getKernelVersion reads composer.json version');
cleanupTempFixture($fixture);

// ===== getKernelVersion: VERSION takes priority over composer.json =====

$fixture = sys_get_temp_dir() . '/version_test_priority_' . uniqid();
mkdir($fixture, 0700, true);
file_put_contents($fixture . '/VERSION', '3.0.0');
file_put_contents($fixture . '/composer.json', json_encode(['name' => 'kernel-web/kernel', 'version' => '2.0.0']));
$vp = new VersionProvider($fixture);
assert_equal('3.0.0', $vp->getKernelVersion(), 'VERSION file takes priority over composer.json');
cleanupTempFixture($fixture);

// ===== getKernelVersion: dev fallback =====

$fixture = sys_get_temp_dir() . '/version_test_dev_' . uniqid();
mkdir($fixture, 0700, true);
$vp = new VersionProvider($fixture);
assert_equal('dev', $vp->getKernelVersion(), 'getKernelVersion returns dev when no files present');
cleanupTempFixture($fixture);

// ===== getKernelVersion: empty VERSION falls through to composer.json =====

$fixture = sys_get_temp_dir() . '/version_test_empty_' . uniqid();
mkdir($fixture, 0700, true);
file_put_contents($fixture . '/VERSION', '');
file_put_contents($fixture . '/composer.json', json_encode(['name' => 'kernel-web/kernel', 'version' => '1.5.0']));
$vp = new VersionProvider($fixture);
assert_equal('1.5.0', $vp->getKernelVersion(), 'Empty VERSION falls through to composer.json');
cleanupTempFixture($fixture);

// ===== getKernelVersion: empty composer.json version falls through to dev =====

$fixture = sys_get_temp_dir() . '/version_test_empty_cv_' . uniqid();
mkdir($fixture, 0700, true);
file_put_contents($fixture . '/composer.json', json_encode(['name' => 'kernel-web/kernel', 'version' => '']));
$vp = new VersionProvider($fixture);
assert_equal('dev', $vp->getKernelVersion(), 'Empty composer.json version falls through to dev');
cleanupTempFixture($fixture);

// ===== getKernelVersion: composer.json without version field =====

$fixture = sys_get_temp_dir() . '/version_test_no_ver_' . uniqid();
mkdir($fixture, 0700, true);
file_put_contents($fixture . '/composer.json', json_encode(['name' => 'kernel-web/kernel']));
$vp = new VersionProvider($fixture);
assert_equal('dev', $vp->getKernelVersion(), 'composer.json without version returns dev');
cleanupTempFixture($fixture);

// ===== getKernelName: from composer.json =====

$fixture = createTempFixture('composer', json_encode(['name' => 'my-custom-kernel', 'version' => '1.0.0']));
$vp = new VersionProvider($fixture);
assert_equal('my-custom-kernel', $vp->getKernelName(), 'getKernelName reads composer.json name');
cleanupTempFixture($fixture);

// ===== getKernelName: default fallback =====

$fixture = sys_get_temp_dir() . '/version_test_kernel_name_' . uniqid();
mkdir($fixture, 0700, true);
$vp = new VersionProvider($fixture);
assert_equal('Kernel-Web', $vp->getKernelName(), 'getKernelName defaults to Kernel-Web');
cleanupTempFixture($fixture);

// ===== getApplicationName: from config =====

$vp = new VersionProvider('/tmp');
assert_equal('MyApp', $vp->getApplicationName(['name' => 'MyApp']), 'getApplicationName reads config name');
assert_equal('Kernel-Web', $vp->getApplicationName([]), 'getApplicationName defaults to Kernel-Web');
assert_equal('Kernel-Web', $vp->getApplicationName(['name' => '']), 'getApplicationName handles empty name');
assert_equal('Fallback', $vp->getApplicationName(['app_name' => 'Fallback']), 'getApplicationName reads app_name fallback');

// ===== getApplicationVersion: from config =====

assert_equal('1.0.0', $vp->getApplicationVersion(['version' => '1.0.0']), 'getApplicationVersion reads config version');
assert_equal('dev', $vp->getApplicationVersion([]), 'getApplicationVersion defaults to dev');
assert_equal('dev', $vp->getApplicationVersion(['version' => '']), 'getApplicationVersion handles empty version');
assert_equal('v2', $vp->getApplicationVersion(['app_version' => 'v2']), 'getApplicationVersion reads app_version fallback');

// ===== getVersions: structure =====

$fixture = createTempFixture('composer', json_encode(['name' => 'test-kernel', 'version' => '3.1.0']));
$vp = new VersionProvider($fixture);
$versions = $vp->getVersions(['name' => 'MyApp', 'version' => '2.0.0']);

assert_equal('test-kernel', $versions['kernel']['name'], 'getVersions kernel name');
assert_equal('3.1.0', $versions['kernel']['version'], 'getVersions kernel version');
assert_equal('MyApp', $versions['application']['name'], 'getVersions application name');
assert_equal('2.0.0', $versions['application']['version'], 'getVersions application version');
assert_false($versions['updates']['configured'], 'getVersions updates configured is false');
assert_null($versions['updates']['kernel_available'], 'getVersions kernel_available is null');
assert_null($versions['updates']['application_available'], 'getVersions application_available is null');
assert_null($versions['updates']['extensions_available'], 'getVersions extensions_available is null');
cleanupTempFixture($fixture);

// ===== getVersions: default app config =====

$fixture = sys_get_temp_dir() . '/version_test_defaults_' . uniqid();
mkdir($fixture, 0700, true);
$vp = new VersionProvider($fixture);
$versions = $vp->getVersions([]);
assert_equal('Kernel-Web', $versions['kernel']['name'], 'getVersions kernel name defaults');
assert_equal('dev', $versions['kernel']['version'], 'getVersions kernel version defaults to dev');
assert_equal('Kernel-Web', $versions['application']['name'], 'getVersions application name defaults');
assert_equal('dev', $versions['application']['version'], 'getVersions application version defaults to dev');
cleanupTempFixture($fixture);

// ===== Constructor: trims trailing slashes =====

$fixture = createTempFixture('composer', json_encode(['name' => 'trim-test', 'version' => '1.0.0']));
$vp = new VersionProvider($fixture . '/');
assert_equal('1.0.0', $vp->getKernelVersion(), 'Constructor trims trailing slash from path');
cleanupTempFixture($fixture);

summary();
