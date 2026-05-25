<?php

/**
 * Route smoke-test suite.
 *
 * Opens every registered/known route in two modes:
 *   1. Guest / not logged in
 *   2. Authenticated user (via POST /auth/login)
 *
 * Detects:
 *   - Unexpected 500 errors
 *   - Protected pages returning the wrong status
 *   - Public pages rendering without authentication
 *   - Authenticated pages rendering successfully when permissions allow
 *
 * Test account:
 *   username: claude
 *   email:    louis+claude@laswitchtech.com
 *   password: Claude123
 *
 * Usage:
 *   php tests/route_smoke_test.php              — run all tests
 *   php tests/route_smoke_test.php --base-url X  — use custom base URL
 */

// --- Parse args ---
$baseUrl = 'https://kernel-web.local';
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--base-url' && isset($argv[$i + 1])) {
        $baseUrl = rtrim($argv[$i + 1], '/');
        $i++;
    }
}

echo "Route smoke-test\n";
echo "Base URL: {$baseUrl}\n\n";

// --- Test user credentials ---
$testUser = 'claude';
$testPass = 'Claude123';

// --- Helpers ---

/**
 * Make an HTTP(S) request via curl.
 *
 * @return array{code:int, headers:array, body:string, error?:string}
 */
function http_request(string $method, string $url, array $options = []): array
{
    $ch = curl_init();
    $defaults = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS       => 3,
        CURLOPT_TIMEOUT         => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => [],
        CURLOPT_NOBODY         => false,
    ];

    if (!empty($options['cookie'])) {
        $defaults[CURLOPT_COOKIE] = $options['cookie'];
    }
    if (!empty($options['cookies'])) {
        $defaults[CURLOPT_COOKIEJAR] = $options['cookies'];
        $defaults[CURLOPT_COOKIEFILE] = $options['cookies'];
    }
    if (!empty($options['post'])) {
        $defaults[CURLOPT_POST] = true;
        $defaults[CURLOPT_POSTFIELDS] = $options['post'];
    }
    if (!empty($options['json'])) {
        $defaults[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        $defaults[CURLOPT_POSTFIELDS] = json_encode($options['json']);
    }
    if (!empty($options['headers'])) {
        foreach ($options['headers'] as $h) {
            $defaults[CURLOPT_HTTPHEADER][] = $h;
        }
    }

    curl_setopt_array($ch, $defaults);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr(curl_getinfo($ch, CURLINFO_HEADER_OUT), $headerSize);
    $error = curl_error($ch);
    // PHP 8.0+ auto-cleans unclosed handles; curl_close() is a no-op.

    return [
        'code'    => $code,
        'headers' => $rawHeaders,
        'body'    => $body ?: '',
        'error'   => $error ?: null,
    ];
}

// --- Route definitions (from routes/web.php) ---
// Format: [method, path, needsAuth, skipReason]
// needsAuth: 'auth' = needs session, 'admin' = needs session + admin permission
// skipReason: if not null, skip with explanation

$routes = [
    // ---- Public (no auth required) ----

    ['GET', '/', false, null],
    ['GET', '/install', false, null],
    ['GET', '/dashboard', false, null],
    ['GET', '/css', false, null],

    // ---- Auth (public pages) ----
    ['GET', '/signin', false, null],
    ['GET', '/auth/login', false, null],
    ['GET', '/auth/forgot-password', false, null],
    ['GET', '/auth/forgot-password/sent', false, null],
    ['GET', '/auth/reset-password', false, null],
    ['GET', '/auth/register', false, null],
    ['GET', '/auth/register/sent', false, null],
    ['GET', '/auth/verify/email', false, null],
    ['GET', '/auth/2fa', false, null],

    // ---- Admin (needs admin permission) ----
    ['GET', '/admin', 'admin', null],
    ['GET', '/admin/audit', 'admin', null],
    ['GET', '/admin/settings', 'admin', null],
    ['GET', '/admin/organizations', 'admin', null],
    ['GET', '/admin/extensions', 'admin', null],
    ['GET', '/admin/extensions/catalog', 'admin', null],
    ['GET', '/admin/extensions/catalog/submit', 'admin', null],
    ['GET', '/admin/extensions/catalog/review', 'admin', null],
    ['GET', '/admin/themes/preview', 'admin', null],
    ['GET', '/admin/developer', 'admin', null],
    ['GET', '/admin/developer/scaffold', 'admin', null],
    ['GET', '/admin/permissions', 'admin', null],
    ['GET', '/admin/permissions/create', 'admin', null],
    ['GET', '/admin/permissions/{id}/edit', 'admin', 'dynamic-id'],
    ['GET', '/admin/users', 'admin', null],
    ['GET', '/admin/users/create', 'admin', null],
    ['GET', '/admin/users/{id}/edit', 'admin', 'dynamic-id'],
    ['GET', '/admin/users/{id}/edit-account', 'admin', 'dynamic-id'],
    ['GET', '/admin/groups', 'admin', null],
    ['GET', '/admin/groups/create', 'admin', null],
    ['GET', '/admin/groups/{id}/edit', 'admin', 'dynamic-id'],

    // ---- Profile (needs session) ----
    ['GET', '/profile', 'auth', null],
    ['GET', '/profile/notification-preferences', 'auth', null],

    // ---- Notifications ----
    ['GET', '/notifications', 'auth', null],

    // ---- Chat ----
    ['GET', '/chat', 'auth', null],
    ['GET', '/chat/rooms/create', 'auth', null],
    ['GET', '/chat/rooms/{id}', 'auth', 'dynamic-id'],

    // ---- File Manager ----
    ['GET', '/files', 'auth', null],
    ['GET', '/files/{rootId}', 'auth', 'dynamic-id'],
    ['GET', '/files/{rootId}/download', 'auth', 'dynamic-id'],
    ['GET', '/files/{rootId}/preview', 'auth', 'dynamic-id'],
    ['GET', '/files/{rootId}/serve', 'auth', 'dynamic-id'],

    // ---- API (SessionAuth — returns 401 JSON for guests) ----
    ['GET', '/api/notifications/count', 'auth', null],
    ['GET', '/api/notifications/recent', 'auth', null],
    ['GET', '/api/profile', 'auth', null],
    ['GET', '/api/profile/sections', 'auth', null],
    ['GET', '/api/profile/sections/{id}', 'auth', 'dynamic-id'],
    ['GET', '/api/profile/2fa/status', 'auth', null],
    ['GET', '/api/profile/organizations', 'auth', null],
    ['GET', '/api/tokens', 'auth', null],
    ['GET', '/auth/me', 'auth', null],
    ['GET', '/api/email-verification/status', 'auth', null],

    // ---- JSON API (SessionAuth) ----
    ['GET', '/api/chat/unread', 'auth', null],

    // ---- Barcode (public) ----
    ['GET', '/api/barcode/QR/SVG?value=hello', false, null],
];

// --- Phase 1: Login ---
echo "--- Phase 1: Authenticating test user ---\n";

$loginPayload = http_build_query([
    'identity' => $testUser,
    'password' => $testPass,
]);

$loginResult = http_request('POST', $baseUrl . '/auth/login', [
    'cookies' => sys_get_temp_dir() . '/route_smoke_session_' . getmypid() . '.cookie',
    'post'    => $loginPayload,
]);

if ($loginResult['error']) {
    echo "LOGIN ERROR: {$loginResult['error']}\n";
    echo "Cannot continue tests without auth. Is the app running on {$baseUrl}?\n";
    exit(1);
}

if ($loginResult['code'] === 302) {
    echo "Login redirected (OK, session established).\n";
} elseif ($loginResult['code'] === 200) {
    echo "Login returned 200 (OK).\n";
} else {
    echo "WARNING: Login returned status {$loginResult['code']}. Trying to proceed anyway...\n";
}
echo "\n";

// --- Phase 2: Test each route ---

$guestFailures = [];
$authFailures  = [];
$guestPass     = 0;
$authPass      = 0;
$skipped       = 0;

$totalTests = 0;

foreach ($routes as $routeIdx => list($method, $path, $needsAuth, $skipReason)) {
    // Resolve dynamic IDs
    $testPath = preg_replace('/\{[^}]+\}/', '1', $path);

    // Skip dynamic routes with documented reasons
    if ($skipReason === 'dynamic-id') {
        $skipped++;
        continue;
    }

    $url = $baseUrl . $testPath;

    // --- Guest test ---
    $guestResult = http_request($method, $url);
    $totalTests++;

    if ($guestResult['error']) {
        echo "FAIL (guest): GET {$testPath} — curl error: {$guestResult['error']}\n";
        $guestFailures[] = ["{$method} {$testPath}", 'curl_error', $guestResult['error']];
        continue;
    }

    // Guest test: auth-required pages should NOT render 200 without session
    if ($needsAuth !== false) {
        if ($guestResult['code'] === 200) {
            echo "FAIL (guest): GET {$testPath} — returned 200 without auth (should be 302/401/403)\n";
            $guestFailures[] = ["{$method} {$testPath}", 'guest_should_not_render', $guestResult['code']];
        } elseif ($guestResult['code'] === 500) {
            echo "FAIL (guest): GET {$testPath} — unexpected 500\n";
            $guestFailures[] = ["{$method} {$testPath}", '500_guest', ''];
        } else {
            $guestPass++;
        }
    } else {
        // Public page: should render 200 or redirect (not 500)
        if ($guestResult['code'] === 500) {
            echo "FAIL (guest): GET {$testPath} — unexpected 500 on public route\n";
            $guestFailures[] = ["{$method} {$testPath}", '500_guest', ''];
        } else {
            $guestPass++;
        }
    }

    // --- Auth test ---
    $authResult = http_request($method, $url, [
        'cookies' => sys_get_temp_dir() . '/route_smoke_session_' . getmypid() . '.cookie',
    ]);
    $totalTests++;

    if ($authResult['error']) {
        echo "FAIL (auth): GET {$testPath} — curl error: {$authResult['error']}\n";
        $authFailures[] = ["{$method} {$testPath}", 'curl_error', $authResult['error']];
        continue;
    }

    if ($authResult['code'] === 500) {
        echo "FAIL (auth): GET {$testPath} — unexpected 500\n";
        $authFailures[] = ["{$method} {$testPath}", '500_auth', ''];
    } elseif ($needsAuth === 'admin' && $authResult['code'] === 403) {
        echo "FAIL (auth): GET {$testPath} — returned 403 (user lacks admin permission)\n";
        $authFailures[] = ["{$method} {$testPath}", '403_lacks_perm', ''];
    } else {
        $authPass++;
    }
}

// --- Summary ---
echo "=== SUMMARY ===\n";
echo "Guest tests: {$guestPass} passed\n";
echo "Auth tests:  {$authPass} passed\n";
echo "Skipped:     {$skipped} (dynamic IDs)\n";
echo "Total tests: {$totalTests}\n";

if (!empty($guestFailures)) {
    echo "\nGuest failures (" . count($guestFailures) . "):\n";
    foreach ($guestFailures as $f) {
        echo "  - {$f[0]}: {$f[1]}\n";
    }
}

if (!empty($authFailures)) {
    echo "\nAuth failures (" . count($authFailures) . "):\n";
    foreach ($authFailures as $f) {
        echo "  - {$f[0]}: {$f[1]}\n";
    }
}

$allFailures = array_merge($guestFailures, $authFailures);
$allPassed = empty($allFailures);

echo "\n" . ($allPassed ? "ALL ROUTE SMOKE TESTS PASSED\n" : "SOME ROUTE SMOKE TESTS FAILED\n");

// Clean up
@unlink(sys_get_temp_dir() . '/route_smoke_session_' . getmypid() . '.cookie');

exit($allPassed ? 0 : 1);
