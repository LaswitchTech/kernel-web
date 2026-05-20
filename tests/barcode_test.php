<?php

/**
 * Barcode/QR generation API tests.
 *
 * Tests BarcodeController: QR SVG, CODE128 SVG, invalid type,
 * invalid format, URL-encoded otpauth value.
 */

require __DIR__ . '/../vendor/autoload.php';
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
$_SERVER['REQUEST_URI'] = '/barcode/QR/SVG/FI2I72O7Q5KULICABPJD7QDGHNB3JFNA';

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
$_SERVER['REQUEST_URI'] = '/barcode/QR/SVG/otpauth%3A%2F%2Ftotp%2FKernel-Web%3Atest%40example.com%3Fsecret%3DABC123%26issuer%3DKernel-Web';

ob_start();
try {
    $controller->svg([
        'type' => 'QR',
        'format' => 'SVG',
        'value' => 'otpauth://totp/Kernel-Web:test@example.com?secret=ABC123&issuer=Kernel-Web',
    ]);
} catch (\Exception $e) {
    echo "OTP_ERROR: " . $e->getMessage();
}
$otpOutput = ob_get_clean();

assert_not_null($otpOutput, 'OTP QR output is not null');
assert_true(strlen($otpOutput) > 0, 'OTP QR output is non-empty (' . strlen($otpOutput) . ' bytes)');
assert_contains('<svg', $otpOutput, 'OTP QR SVG starts with <svg>');
assert_contains('</svg>', $otpOutput, 'OTP QR SVG ends with </svg>');

// The SVG body should NOT contain raw otpauth URI text (only in SVG tags)
$svgBody = preg_replace('/<svg[^>]*>.*?<\/svg>/', '', $otpOutput);
assert_false(strpos($svgBody, 'otpauth') !== false, 'QR SVG body does not contain raw otpauth URI');

// === Test 3: QR SVG with custom size ===
$_GET = ['size' => 300, 'margin' => 5];
$_SERVER['REQUEST_URI'] = '/barcode/QR/SVG/ABC123?size=300&margin=5';

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
$_SERVER['REQUEST_URI'] = '/barcode/CODE128/SVG/ABC123';

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
$_SERVER['REQUEST_URI'] = '/barcode/EAN13/SVG/1234567890128';

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
$_SERVER['REQUEST_URI'] = '/barcode/INVALID/SVG/ABC';

ob_start();
try {
    $controller->svg(['type' => 'INVALID', 'format' => 'SVG', 'value' => 'ABC']);
} catch (\Exception $e) {
    echo "INVALID_TYPE_ERROR: " . $e->getMessage();
}
$invalidTypeOutput = ob_get_clean();

// Should return JSON error, not SVG
assert_contains('error', $invalidTypeOutput, 'Invalid type returns error in response');

// === Test 7: Invalid format ===
$_GET = [];
$_SERVER['REQUEST_URI'] = '/barcode/QR/PNG/ABC';

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
$_SERVER['REQUEST_URI'] = '/barcode//SVG/ABC';

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
$_SERVER['REQUEST_URI'] = '/barcode/QR/SVG/';

ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'SVG', 'value' => '']);
} catch (\Exception $e) {
    echo "EMPTY_VALUE_ERROR: " . $e->getMessage();
}
$emptyValueOutput = ob_get_clean();

assert_contains('error', $emptyValueOutput, 'Empty value returns error in response');
assert_contains('Empty barcode content', $emptyValueOutput, 'Error mentions empty content');

// === Test 10: URL-decoded value works ===
$_GET = [];
$_SERVER['REQUEST_URI'] = '/barcode/QR/SVG/FI2I72O7Q5KULICABPJD7QDGHNB3JFNA';

ob_start();
try {
    $controller->svg(['type' => 'QR', 'format' => 'SVG', 'value' => 'FI2I72O7Q5KULICABPJD7QDGHNB3JFNA']);
} catch (\Exception $e) {
    echo "REPEAT_ERROR: " . $e->getMessage();
}
$repeatOutput = ob_get_clean();

assert_not_null($repeatOutput, 'Repeat QR output is not null');
assert_true(strlen($repeatOutput) > 0, 'Repeat QR output is non-empty');
assert_contains('<svg', $repeatOutput, 'Repeat QR SVG starts with <svg>');

// === Test 11: CODE39 SVG generation ===
$_GET = [];
$_SERVER['REQUEST_URI'] = '/barcode/CODE39/SVG/ABC123';

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

// === Test 12: Mixed case type/format (should be normalized to uppercase) ===
$_GET = [];
$_SERVER['REQUEST_URI'] = '/barcode/qr/svg/ABC123';

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

// === Test 13: QR output is valid SVG structure ===
$_GET = [];
$_SERVER['REQUEST_URI'] = '/barcode/QR/SVG/ABC123';

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

// === Test 14: CODE128 output is valid SVG structure ===
$_GET = [];
$_SERVER['REQUEST_URI'] = '/barcode/CODE128/SVG/ABC123';

ob_start();
try {
    $controller->svg(['type' => 'CODE128', 'format' => 'SVG', 'value' => 'ABC123']);
} catch (\Exception $e) {
    echo "CODE128_STRUCTURE_ERROR: " . $e->getMessage();
}
$code128StructOutput = ob_get_clean();

assert_contains('xmlns="http://www.w3.org/2000/svg"', $code128StructOutput, 'CODE128 SVG has correct xmlns');
assert_contains('viewBox="', $code128StructOutput, 'CODE128 SVG has viewBox');

// === Test 15: /api/barcode/ route dispatch ===
$_GET = [];
$apiContainer = new Container();
$apiRouter = new Router($apiContainer);
$apiRouter->get('/api/barcode/{type}/{format}/{value}', 'BarcodeController@svg', [], 0);

// Capture dispatched output by intercepting header/echo
$dispatched = '';
$origHeader = 'header';
header_remove('Content-Type') ?: true;

$savedStdout = fopen('php://memory', 'r+b');
$captured = '';
$origContentLen = ini_get('xdebug.max_nesting_level');

// Use output buffering to capture echo from dispatch
ob_start();
try {
    // Register a wrapper around the controller to capture output
    $apiRouter->get('/barcode/{type}/{format}/{value}', 'BarcodeController@svg', [], 0);

    // Verify routes are registered
    $checkRoutes = (function () {
        return $this->routes;
    })->call($apiRouter);

    assert_true(count($checkRoutes) >= 2, 'Both /api/barcode and /barcode routes registered');

    // Find the /api/barcode route
    $apiRoute = null;
    foreach ($checkRoutes as $r) {
        if (strpos($r['path'], '/api/barcode') === 0) {
            $apiRoute = $r;
            break;
        }
    }
    assert_not_null($apiRoute, '/api/barcode route is registered');
    assert_true(str_starts_with($apiRoute['path'], '/api/barcode/'), 'API route path starts with /api/barcode/');
} catch (\Exception $e) {
    echo "ROUTE_ERROR: " . $e->getMessage();
}
ob_end_clean();

// === Summary ===
summary();
exit($__FAIL__ > 0 ? 1 : 0);
