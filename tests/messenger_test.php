<?php

/**
 * Messenger core tests — zero dependencies, fake transport behavior.
 *
 * Run: php tests/messenger_test.php
 * Assertions: 22
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/MessengerException.php';
require_once __DIR__ . '/../app/Core/MessengerTransportInterface.php';
require_once __DIR__ . '/../app/Core/Message.php';
require_once __DIR__ . '/../app/Core/Messenger.php';

use App\Core\Messenger;
use App\Core\MessengerException;
use App\Core\MessengerTransportInterface;
use App\Core\Message;

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

function assert_false($val, string $msg): void
{
    global $passed, $failed, $total;
    $total++;
    if ($val === false) {
        $passed++;
    } else {
        $failed++;
        echo "FAIL: $msg (expected false, got " . var_export($val, true) . ")\n";
    }
}

// ------ Message immutability tests ------

$msg = new Message(to: '+15551234567', from: '+15559876543', body: 'Hello');
assert_eq($msg->to, '+15551234567', 'Message::to');
assert_eq($msg->from, '+15559876543', 'Message::from');
assert_eq($msg->body, 'Hello', 'Message::body');
assert_eq($msg->template, '', 'Message::template default');
assert_eq($msg->context, [], 'Message::context default');
assert_eq($msg->media, [], 'Message::media default');

// withMedia returns a new instance (immutability)
$msg2 = $msg->withMedia('https://example.com/img.jpg', 'image/jpeg');
assert_true($msg2 !== $msg, 'withMedia returns new instance');
assert_eq($msg2->media[0]['url'], 'https://example.com/img.jpg', 'withMedia url');
assert_eq($msg2->media[0]['mime_type'], 'image/jpeg', 'withMedia mime_type');
assert_eq($msg->media, [], 'withMedia immutability — original untouched');

// withBody returns a new instance
$msg3 = $msg->withBody('New body');
assert_true($msg3 !== $msg, 'withBody returns new instance');
assert_eq($msg3->body, 'New body', 'withBody body');
assert_eq($msg->body, 'Hello', 'withBody immutability — original untouched');

// ------ Messenger + fake transport tests ------

class FakeTransport implements MessengerTransportInterface
{
    public bool $sent = false;
    public ?Message $lastMessage = null;

    public function send(Message $message): bool
    {
        $this->sent = true;
        $this->lastMessage = $message;
        return true;
    }

    public function identifier(): string
    {
        return 'fake';
    }
}

$fake = new FakeTransport();
$messenger = new Messenger($fake);
assert_true($messenger->send(new Message(to: '+1', from: '+2', body: 'x')), 'Messenger sends via transport');
assert_true($fake->sent, 'Fake transport was called');
assert_eq($fake->lastMessage?->to, '+1', 'Message delivered to correct recipient');
assert_eq($messenger->transportIdentifier(), 'fake', 'transportIdentifier returns transport name');
assert_true($messenger->hasTransport(), 'hasTransport returns true with transport');

// Null transport
$noTransport = new Messenger();
assert_false($noTransport->hasTransport(), 'hasTransport returns false without transport');
assert_eq($noTransport->transportIdentifier(), null, 'transportIdentifier returns null without transport');
assert_throws(MessengerException::class, fn() => $noTransport->send(new Message(to: '+1', from: '+2', body: 'x')), 'send throws with no transport');

// setTransport swaps the transport
$messenger->setTransport(new FakeTransport());
assert_true($messenger->hasTransport(), 'hasTransport after setTransport');

echo "\n=== Messenger Tests ===\n";
echo "Passed: $passed / $total\n";
echo "Failed: $failed / $total\n";

exit($failed > 0 ? 1 : 0);
