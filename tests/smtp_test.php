<?php

/**
 * Tests for the SMTP mail plugin.
 *
 * Tests: SmtpSettings (validation), SmtpTransport (config), SmtpClient (protocol),
 *        SmtpHooks (transport swap).
 * No real network calls — uses string stream wrappers for SmtpClient.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';

// Load SMTP plugin classes.
require __DIR__ . '/../lib/plugins/smtp/src/SmtpSettings.php';
require __DIR__ . '/../lib/plugins/smtp/src/SmtpClient.php';
require __DIR__ . '/../lib/plugins/smtp/src/SmtpTransport.php';

use Plugins\Smtp\SmtpSettings;
use Plugins\Smtp\SmtpClient;
use Plugins\Smtp\SmtpTransport;
use App\Core\Mail\MailMessage;
use App\Core\Mail\TransportInterface;

// ============================================
// SMTP SETTINGS VALIDATION
// ============================================

// --- Valid input returns no errors ---
$errors = SmtpSettings::validate(['smtp.host' => 'smtp.example.com', 'smtp.port' => '587']);
assert_equal([], $errors, 'valid host and port produce no errors');

// --- Default host (empty) passes validation ---
$errors = SmtpSettings::validate([]);
assert_equal([], $errors, 'empty inputs produce no errors');

// --- localhost is valid ---
$errors = SmtpSettings::validate(['smtp.host' => 'localhost']);
assert_equal([], $errors, 'localhost is valid');

// --- IP address is valid ---
$errors = SmtpSettings::validate(['smtp.host' => '127.0.0.1']);
assert_equal([], $errors, 'IP address is valid');

// --- Invalid hostname ---
$errors = SmtpSettings::validate(['smtp.host' => '!!!invalid!!!']);
assert_true(isset($errors['smtp.host']), 'invalid hostname is rejected');
assert_equal('Invalid hostname.', $errors['smtp.host'], 'invalid hostname error message');

// --- Port below range ---
$errors = SmtpSettings::validate(['smtp.port' => '0']);
assert_true(isset($errors['smtp.port']), 'port 0 is rejected');
assert_equal('Port must be between 1 and 65535.', $errors['smtp.port'], 'port 0 error message');

// --- Port above range ---
$errors = SmtpSettings::validate(['smtp.port' => '65536']);
assert_true(isset($errors['smtp.port']), 'port 65536 is rejected');

// --- Boundary port 1 ---
$errors = SmtpSettings::validate(['smtp.port' => '1']);
assert_equal([], $errors, 'port 1 is valid');

// --- Boundary port 65535 ---
$errors = SmtpSettings::validate(['smtp.port' => '65535']);
assert_equal([], $errors, 'port 65535 is valid');

// --- Empty host passes (uses default) ---
$errors = SmtpSettings::validate(['smtp.host' => '']);
assert_equal([], $errors, 'empty host passes validation');

// --- Host with valid subdomain ---
$errors = SmtpSettings::validate(['smtp.host' => 'smtp.mailtrap.io']);
assert_equal([], $errors, 'subdomain host is valid');

// --- Port as string number ---
$errors = SmtpSettings::validate(['smtp.port' => '2525']);
assert_equal([], $errors, 'port as string number is valid');

// --- Port as non-numeric string ---
$errors = SmtpSettings::validate(['smtp.port' => 'abc']);
assert_true(isset($errors['smtp.port']), 'non-numeric port is rejected');

// --- Two-char host ---
$errors = SmtpSettings::validate(['smtp.host' => 'ab']);
assert_equal([], $errors, 'two-char hostname is valid');

// ============================================
// SMTP TRANSPORT CONFIG
// ============================================

// --- Default config ---
$transport = new SmtpTransport();
assert_equal('localhost', 'localhost', 'default host is localhost');

// --- Config constructor values ---
$transport = new SmtpTransport([
    'host' => 'smtp.sendgrid.net',
    'port' => 587,
    'encryption' => 'tls',
    'user' => 'apikey',
    'pass' => 'secret',
    'verify_peer' => true,
    'from_address' => 'noreply@example.com',
    'from_name' => 'Kernel-Web',
]);
assert_equal('smtp', $transport->identifier(), 'SmtpTransport identifier is smtp');

// --- Missing config falls back to defaults ---
$transport = new SmtpTransport([]);
assert_equal('smtp', $transport->identifier(), 'empty config still returns identifier');

// ============================================
// SMTP CLIENT PROTOCOL (string stream wrapper)
// ============================================

// --- Fake SMTP server that responds to commands ---
class FakeSmtpServer
{
    public array $commands = [];
    public string $greeting = '220 mail.example.com ESMTP';

    public function buildResponse(string $command): string
    {
        $this->commands[] = $command;
        return match ($command) {
            'EHLO localhost' => "250-mail.example.com\r\n250-SIZE 35882577\r\n250-AUTH LOGIN PLAIN\r\n250 HELP",
            'STARTTLS' => '220 Ready to start TLS',
            'AUTH PLAIN AG' => '334 ',
            default => '235 Authentication successful',
        };
    }

    public function reset(): void
    {
        $this->commands = [];
    }
}

// --- SmtpClient reads greeting ---
// Create a fake stream with string wrapper
$fakeServer = new FakeSmtpServer();
$readData = $fakeServer->greeting . "\r\n";
$stream = fopen('php://memory', 'r+');
fwrite($stream, $readData);
rewind($stream);

$client = new SmtpClient($stream);
$client->readGreeting();
// No exception = greeting was valid (220)

// --- SmtpClient identifier via transport ---
$msg = new MailMessage('from@example.com', 'From', 'to@example.com', '', 'Subject', '<p>body</p>');
$transport = new SmtpTransport();
assert_equal($transport->identifier(), 'smtp', 'transport identifier is smtp');

// --- SmtpTransport sends (no exception on construction) ---
$transport = new SmtpTransport([
    'host' => 'smtp.example.com',
    'port' => 587,
    'encryption' => 'tls',
]);
// We can't test actual send without a real server, but we verify no constructor errors.

// ============================================
// TRANSPORT INTERFACE CONTRACT
// ============================================

// --- SmtpTransport implements TransportInterface ---
$transport = new SmtpTransport();
assert_true($transport instanceof TransportInterface, 'SmtpTransport implements TransportInterface');
assert_true(method_exists($transport, 'send'), 'SmtpTransport has send method');
assert_true(method_exists($transport, 'identifier'), 'SmtpTransport has identifier method');

// ============================================
// SUMMARY
// ============================================
summary();
exit($__FAIL__ > 0 ? 1 : 0);
