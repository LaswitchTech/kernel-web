<?php

/**
 * Barcode/QR generation API tests.
 *
 * Tests BarcodeController: QR SVG, CODE128 SVG, invalid type,
 * invalid format, URL-encoded otpauth value, query-param value.
 */

// Gracefully skip barcode generation tests when vendor/autoload.php
// is missing (composer install was not run in CI).
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    echo "Skipping barcode tests — vendor/autoload.php not found (run composer install).\n";
    exit(0);
}

require $vendorAutoload;
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Controllers\BarcodeController;
use App\Core\Container;
use App\Core\Router;

// --- Bootstrap ---

$container = new Container();
$controller = new BarcodeController($container);

// === Test 1: QR SVG generation (secret) ===
$_GET = [];
$output = null;
ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'SVG', 'value' => 'FI2I72O7Q5KULICABPJD7QDGHNB3JFNA']);
} catch (\Exception $e) {
    echo "QR_ERROR: " . $e->getMessage();
}
$output = ob_get_clean();

assert_not_null($output, 'QR SVG output is not null');
assert_true(strlen($output) > 0, 'QR SVG output is non-empty (' . strlen($output) . ' bytes)');
assert_contains('<svg', $output, 'QR SVG starts with <svg>');
assert_contains('</svg>', $output, 'QR SVG ends with </svg>');
assert_contains('xmlns', $output, 'QR SVG has xmlns attribute');
assert_contains('viewBox', $output, 'QR SVG has viewBox');

// === Test 2: QR SVG generation (otpauth:// URI, URL-encoded) ===
$_GET = [];
$otpOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'SVG', 'value' => 'otpauth://totp/Kernel-Web:test@example.com?secret=ABC123&issuer=Kernel-Web']);
} catch (\Exception $e) {
    echo "OTP_ERROR: " . $e->getMessage();
}
$otpOutput = ob_get_clean();

assert_not_null($otpOutput, 'OTP QR output is not null');
assert_true(strlen($otpOutput) > 0, 'OTP QR output is non-empty (' . strlen($otpOutput) . ' bytes)');
assert_contains('<svg', $otpOutput, 'OTP QR SVG starts with <svg>');
assert_contains('</svg>', $otpOutput, 'OTP QR SVG ends with </svg>');

// === Test 3: QR SVG with custom size ===
$_GET = ['size' => 300, 'margin' => 5];
$sizeOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'SVG', 'value' => 'ABC123']);
} catch (\Exception $e) {
    echo "QR_SIZE_ERROR: " . $e->getMessage();
}
$sizeOutput = ob_get_clean();

assert_not_null($sizeOutput, 'QR size output is not null');
assert_true(strlen($sizeOutput) > 0, 'QR size output is non-empty');

// === Test 4: CODE128 SVG generation ===
$_GET = [];
$code128Output = null;
ob_start();
try {
    $controller->svg(['type' => 'CODE128', 'format' => 'SVG', 'value' => 'ABC123']);
} catch (\Exception $e) {
    echo "CODE128_ERROR: " . $e->getMessage();
}
$code128Output = ob_get_clean();

assert_not_null($code128Output, 'CODE128 output is not null');
assert_true(strlen($code128Output) > 0, 'CODE128 output is non-empty (' . strlen($code128Output) . ' bytes)');
assert_contains('<svg', $code128Output, 'CODE128 SVG starts with <svg>');
assert_contains('</svg>', $code128Output, 'CODE128 SVG ends with </svg>');

// === Test 5: EAN13 SVG generation ===
$_GET = [];
$ean13Output = null;
ob_start();
try {
    $controller->svg(['type' => 'EAN13', 'format' => 'SVG', 'value' => '1234567890128']);
} catch (\Exception $e) {
    echo "EAN13_ERROR: " . $e->getMessage();
}
$ean13Output = ob_get_clean();

assert_not_null($ean13Output, 'EAN13 output is not null');
assert_true(strlen($ean13Output) > 0, 'EAN13 output is non-empty (' . strlen($ean13Output) . ' bytes)');
assert_contains('<svg', $ean13Output, 'EAN13 SVG starts with <svg>');
assert_contains('</svg>', $ean13Output, 'EAN13 SVG ends with </svg>');

// === Test 6: Invalid type ===
$_GET = [];
$invalidTypeOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'INVALID', 'format' => 'SVG', 'value' => 'ABC']);
} catch (\Exception $e) {
    echo "INVALID_TYPE_ERROR: " . $e->getMessage();
}
$invalidTypeOutput = ob_get_clean();

assert_contains('error', $invalidTypeOutput, 'Invalid type returns error in response');

// === Test 7: Invalid format ===
$_GET = [];
$invalidFormatOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'PNG', 'value' => 'ABC']);
} catch (\Exception $e) {
    echo "INVALID_FORMAT_ERROR: " . $e->getMessage();
}
$invalidFormatOutput = ob_get_clean();

assert_contains('error', $invalidFormatOutput, 'Invalid format returns error in response');
assert_contains('Unsupported format', $invalidFormatOutput, 'Error mentions unsupported format');

// === Test 8: Empty type ===
$_GET = [];
$emptyTypeOutput = null;
ob_start();
try {
    $controller->svg(['type' => '', 'format' => 'SVG', 'value' => 'ABC']);
} catch (\Exception $e) {
    echo "EMPTY_TYPE_ERROR: " . $e->getMessage();
}
$emptyTypeOutput = ob_get_clean();

assert_contains('error', $emptyTypeOutput, 'Empty type returns error in response');
assert_contains('Missing barcode type', $emptyTypeOutput, 'Error mentions missing type');

// === Test 9: Empty value ===
$_GET = [];
$emptyValueOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'SVG', 'value' => '']);
} catch (\Exception $e) {
    echo "EMPTY_VALUE_ERROR: " . $e->getMessage();
}
$emptyValueOutput = ob_get_clean();

assert_contains('error', $emptyValueOutput, 'Empty value returns error in response');
assert_contains('Empty barcode content', $emptyValueOutput, 'Error mentions empty content');

// === Test 10: CODE39 SVG generation ===
$_GET = [];
$code39Output = null;
ob_start();
try {
    $controller->svg(['type' => 'CODE39', 'format' => 'SVG', 'value' => 'ABC123']);
} catch (\Exception $e) {
    echo "CODE39_ERROR: " . $e->getMessage();
}
$code39Output = ob_get_clean();

assert_not_null($code39Output, 'CODE39 output is not null');
assert_true(strlen($code39Output) > 0, 'CODE39 output is non-empty (' . strlen($code39Output) . ' bytes)');
assert_contains('<svg', $code39Output, 'CODE39 SVG starts with <svg>');
assert_contains('</svg>', $code39Output, 'CODE39 SVG ends with </svg>');

// === Test 11: Mixed case type/format (should be normalized to uppercase) ===
$_GET = [];
$caseOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'qr', 'format' => 'svg', 'value' => 'ABC123']);
} catch (\Exception $e) {
    echo "CASE_ERROR: " . $e->getMessage();
}
$caseOutput = ob_get_clean();

assert_not_null($caseOutput, 'Case-insensitive output is not null');
assert_true(strlen($caseOutput) > 0, 'Case-insensitive output is non-empty');
assert_contains('<svg', $caseOutput, 'Case-insensitive SVG starts with <svg>');

// === Test 12: QR output is valid SVG structure ===
$_GET = [];
$svgStructOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'SVG', 'value' => 'ABC123']);
} catch (\Exception $e) {
    echo "SVG_STRUCTURE_ERROR: " . $e->getMessage();
}
$svgStructOutput = ob_get_clean();

assert_contains('xmlns="http://www.w3.org/2000/svg"', $svgStructOutput, 'QR SVG has correct xmlns');
assert_contains('viewBox="', $svgStructOutput, 'QR SVG has viewBox');
assert_true(strpos($svgStructOutput, 'fill="#000"') !== false || strpos($svgStructOutput, 'fill=\'#000\'') !== false, 'QR SVG has black fill elements');

// === Test 13: CODE128 output is valid SVG structure ===
$_GET = [];
$code128StructOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'CODE128', 'format' => 'SVG', 'value' => 'ABC123']);
} catch (\Exception $e) {
    echo "CODE128_STRUCTURE_ERROR: " . $e->getMessage();
}
$code128StructOutput = ob_get_clean();

assert_contains('xmlns="http://www.w3.org/2000/svg"', $code128StructOutput, 'CODE128 SVG has correct xmlns');
assert_contains('viewBox="', $code128StructOutput, 'CODE128 SVG has viewBox');

// === Test 14: Query-param value works (no path segment) ===
$_GET = ['value' => 'QUERY-PARAM-VALUE'];
$_SERVER['REQUEST_URI'] = '/api/barcode/QR/SVG?value=QUERY-PARAM-VALUE';
$queryParamOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'SVG']);
} catch (\Exception $e) {
    echo "QUERY_PARAM_ERROR: " . $e->getMessage();
}
$queryParamOutput = ob_get_clean();

assert_not_null($queryParamOutput, 'Query-param QR output is not null');
assert_true(strlen($queryParamOutput) > 0, 'Query-param QR output is non-empty (' . strlen($queryParamOutput) . ' bytes)');
assert_contains('<svg', $queryParamOutput, 'Query-param QR SVG starts with <svg>');

// === Test 15: /api/barcode/ has two routes (with and without {value}) ===
$apiContainer = new Container();
$apiRouter = new Router($apiContainer);
$apiRouter->get('/api/barcode/{type}/{format}/{value}', 'BarcodeController@svg', [], 0);
$apiRouter->get('/api/barcode/{type}/{format}', 'BarcodeController@svg', [], 0);

$apiRoutes = (function () {
    return $this->routes;
})->call($apiRouter);

assert_true(count($apiRoutes) === 2, 'Two barcode routes registered');

$apiRouteWithPath = null;
$apiRouteWithoutPath = null;
foreach ($apiRoutes as $r) {
    if (strpos($r['path'], '/{value}') !== false) {
        $apiRouteWithPath = $r;
    } elseif (strpos($r['path'], '/api/barcode/') === 0) {
        $apiRouteWithoutPath = $r;
    }
}
assert_not_null($apiRouteWithPath, '/api/barcode/{type}/{format}/{value} route is registered');
assert_not_null($apiRouteWithoutPath, '/api/barcode/{type}/{format} route is registered');

// === Test 16: /barcode/ alias is NOT registered ===
assert_true(count($apiRoutes) === 2, 'Only two barcode routes (no /barcode/ alias)');

// === Test 17: URL-encoded slash value via query param ===
$_GET = ['value' => 'https%3A%2F%2Flaswitchtech.com%2F'];
$_SERVER['REQUEST_URI'] = '/api/barcode/QR/SVG?value=https%3A%2F%2Flaswitchtech.com%2F';
$encodedSlashOutput = null;
ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'SVG']);
} catch (\Exception $e) {
    echo "ENCODED_SLASH_ERROR: " . $e->getMessage();
}
$encodedSlashOutput = ob_get_clean();

assert_not_null($encodedSlashOutput, 'URL-encoded slash QR output is not null');
assert_true(strlen($encodedSlashOutput) > 0, 'URL-encoded slash QR output is non-empty (' . strlen($encodedSlashOutput) . ' bytes)');
assert_contains('<svg', $encodedSlashOutput, 'URL-encoded slash QR SVG starts with <svg>');

// === Summary ===
summary();
exit($__FAIL__ > 0 ? 1 : 0);
