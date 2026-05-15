<?php

/**
 * Telico plugin tests — zero dependencies, fake API behavior.
 *
 * Run: php lib/plugins/telico/tests/telico_test.php
 * Assertions: 12
 */

declare(strict_types=1);

$base    = dirname(dirname(dirname(dirname(__DIR__))));
require_once $base . '/app/Core/MessengerException.php';
require_once $base . '/app/Core/MessengerTransportInterface.php';
require_once __DIR__ . '/../src/TelicoApiClient.php';
require_once __DIR__ . '/../src/TelicoTransport.php';

use Plugins\Telico\TelicoApiClient;
use Plugins\Telico\TelicoTransport;

$passed = 0;
$failed = 0;
$total  = 0;

function assert_eq($a, $b, string $msg): void
{
    global $passed, $failed, $total;
    $total++;
    if ($a === $b) {
        $passed++;
    } else {
        $failed++;
        echo "FAIL: $msg (expected " . var_export($b, true) . ", got " . var_export($a, true) . ")\n";
    }
}

function assert_throws(string $cls, callable $fn, string $msg): void
{
    global $passed, $failed, $total;
    $total++;
    try {
        $fn();
        $failed++;
        echo "FAIL: $msg (expected $cls, no exception thrown)\n";
    } catch (Throwable $e) {
        if ($e instanceof $cls) {
            $passed++;
        } else {
            $failed++;
            echo "FAIL: $msg (expected $cls, got " . get_class($e) . ")\n";
        }
    }
}

function assert_true($val, string $msg): void
{
    global $passed, $failed, $total;
    $total++;
    if ($val === true) {
        $passed++;
    } else {
        $failed++;
        echo "FAIL: $msg (expected true, got " . var_export($val, true) . ")\n";
    }
}

// ------ TelicoApiClient tests (credential validation only — no network calls) ------

// Missing credentials
$client = new TelicoApiClient([]);
assert_throws(\App\Core\MessengerException::class,
    fn() => $client->sendSms('+15551234567', 'test'),
    'sendSms throws without credentials');

$client2 = new TelicoApiClient(['telico.username' => 'user', 'telico.sms_pass' => 'pass']);
assert_throws(\App\Core\MessengerException::class,
    fn() => $client2->sendSms('+15551234567', 'test'),
    'sendSms throws without caller ID');

// Valid config — constructible
$client3 = new TelicoApiClient([
    'telico.username' => 'user',
    'telico.sms_pass' => 'pass',
    'telico.callerid' => '+15551234567',
]);
assert_true(true, 'TelicoApiClient constructs with valid config');

// getConversations returns empty array (no network, but the curl_exec returns false → json_decode of false returns null ?? [])
// We can't test the actual API call without a real endpoint, so verify the class structure.
assert_true(method_exists($client3, 'getConversations'), 'TelicoApiClient has getConversations');
assert_true(method_exists($client3, 'getMessages'), 'TelicoApiClient has getMessages');

// ------ TelicoTransport tests ------

$config = [
    'telico.username' => 'user',
    'telico.sms_pass' => 'pass',
    'telico.callerid' => '+15551234567',
];
$transport = new TelicoTransport($config);
assert_eq($transport->identifier(), 'telico', 'Transport identifier is telico');

// The transport delegates to TelicoApiClient, which throws MessengerException without credentials.
// With full config it would make a curl call (which we can't test without a server),
// so we verify the class structure instead.
assert_true(method_exists($transport, 'send'), 'TelicoTransport has send method');

// ------ TelicoSettings class structure check ------

$settingsFile = dirname(__DIR__) . '/src/TelicoSettings.php';
assert_true(file_exists($settingsFile), 'TelicoSettings.php file exists');
if (file_exists($settingsFile)) {
    $content = file_get_contents($settingsFile);
    assert_true(str_contains($content, 'public static function register'), 'TelicoSettings has register method');
    assert_true(str_contains($content, 'public static function render'), 'TelicoSettings has render method');
    assert_true(str_contains($content, 'public static function validate'), 'TelicoSettings has validate method');
    assert_true(str_contains($content, 'public static function save'), 'TelicoSettings has save method');
}

if ($failed > 0) {
    echo "FAILED\n";
} else {
    echo "ALL PASSED\n";
}

exit($failed > 0 ? 1 : 0);
