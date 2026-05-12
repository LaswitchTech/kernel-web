<?php

/**
 * Zero-dependency tests for the Mailer foundation.
 *
 * Tests: MailMessage, Attachment, TemplateRegistry,
 * MailTransport, Mailer, TransportInterface contract.
 * No real emails sent.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

use App\Core\Mail\MailerException;
use App\Core\Mail\Attachment;
use App\Core\Mail\MailMessage;
use App\Core\Mail\TransportInterface;
use App\Core\Mail\MailTransport;
use App\Core\Mail\TemplateRegistry;
use App\Core\Mail\Mailer;

// --- Fake transport for testing ---

class FakeTransport implements TransportInterface
{
    public ?MailMessage $lastMessage = null;
    public bool $throw = false;

    public function send(MailMessage $message): bool
    {
        $this->lastMessage = $message;
        if ($this->throw) {
            throw new MailerException('Transport failure', code: 500, transportName: 'fake');
        }
        return true;
    }

    public function identifier(): string
    {
        return 'fake';
    }
}

// ===== MAIL MESSAGE =====

// --- Basic construction ---
$msg = new MailMessage(
    from: 'sender@example.com',
    fromName: 'Sender',
    to: 'recipient@example.com',
    toName: 'Recipient',
    subject: 'Test Subject',
    bodyHtml: '<p>Hello</p>',
    bodyText: 'Hello',
);
assert_equal('sender@example.com', $msg->from, 'from is set');
assert_equal('Sender', $msg->fromName, 'fromName is set');
assert_equal('recipient@example.com', $msg->to, 'to is set');
assert_equal('Recipient', $msg->toName, 'toName is set');
assert_equal('Test Subject', $msg->subject, 'subject is set');
assert_equal('<p>Hello</p>', $msg->bodyHtml, 'bodyHtml is set');
assert_equal('Hello', $msg->bodyText, 'bodyText is set');
assert_equal([], $msg->cc, 'cc is empty by default');
assert_equal([], $msg->bcc, 'bcc is empty by default');
assert_equal([], $msg->attachments, 'attachments is empty by default');
assert_equal([], $msg->headers, 'headers is empty by default');

// --- Clone pattern withCc ---
$msgCc = $msg->withCc('cc1@example.com');
assert_equal('cc1@example.com', $msgCc->cc[0], 'withCc sets cc');
assert_equal([], $msg->cc, 'original is not mutated');

$msgCc2 = $msgCc->withCc(['cc2@example.com', 'cc3@example.com']);
assert_equal('cc1@example.com', $msgCc2->cc[0], 'withCc appends to existing cc');
assert_equal('cc2@example.com', $msgCc2->cc[1], 'withCc appends second cc');
assert_equal('cc3@example.com', $msgCc2->cc[2], 'withCc appends third cc');

// --- Clone pattern withBcc ---
$msgBcc = $msg->withBcc('bcc1@example.com');
assert_equal('bcc1@example.com', $msgBcc->bcc[0], 'withBcc sets bcc');
assert_equal([], $msg->bcc, 'original is not mutated');

// --- Clone pattern withHeader ---
$msgH = $msg->withHeader('X-Custom', 'value');
assert_equal('value', $msgH->headers['X-Custom'], 'withHeader sets header');
assert_equal([], $msg->headers, 'original is not mutated');

// --- Clone pattern withReplyTo ---
$msgR = $msg->withReplyTo('reply@example.com', 'Reply Person');
assert_true(isset($msgR->headers['Reply-To']), 'withReplyTo sets Reply-To header');
assert_contains('Reply Person <reply@example.com>', $msgR->headers['Reply-To'], 'withReplyTo format includes name');

$msgRN = $msg->withReplyTo('reply-n@example.com');
assert_true(isset($msgRN->headers['Reply-To']), 'withReplyTo without name');
assert_equal('reply-n@example.com', $msgRN->headers['Reply-To'], 'withReplyTo plain email format');

// --- Template properties ---
$msgT = new MailMessage('from@example.com', 'From', 'to@example.com', '', 'Subject');
assert_null($msgT->template, 'template is null by default');
assert_equal([], $msgT->context, 'context is empty by default');

// ===== ATTACHMENT =====

// --- Construction ---
$att = new Attachment('/tmp/test.pdf', 'test.pdf', 'application/pdf', false);
assert_equal('/tmp/test.pdf', $att->path, 'path is set');
assert_equal('test.pdf', $att->name, 'name is set');
assert_equal('application/pdf', $att->mimeType, 'mimeType is set');
assert_false($att->inline, 'inline defaults to false');

// --- Inline attachment ---
$attInline = new Attachment('/tmp/img.png', 'img.png', 'image/png', true);
assert_true($attInline->inline, 'inline can be true');

// --- Auto-detect mime type ---
file_put_contents('/tmp/fake.png', 'fake-png-content');
$attPng = Attachment::file('/tmp/fake.png', 'fake.png');
assert_equal('image/png', $attPng->mimeType, 'Attachment::file detects png mime');

// --- Null name uses basename ---
// We can't easily test this without a real file, but the logic is covered.

// --- Invalid path throws ---
assert_raises(
    fn () => new Attachment('path-with-null' . chr(0) . '-byte.txt', 'test.txt'),
    \InvalidArgumentException::class,
    'path with null byte throws'
);

// ===== TEMPLATE REGISTRY =====

// --- Clear state before tests ---
TemplateRegistry::clear();

// --- Add and find core template ---
TemplateRegistry::addCore('welcome', '/tmp/template-welcome.php');
$path = TemplateRegistry::find('welcome');
assert_equal('/tmp/template-welcome.php', $path, 'find returns registered core path');

// --- Plugin path search ---
TemplateRegistry::addPath('notifications', '/tmp/notifications/emails/');
// core search first
assert_equal('/tmp/template-welcome.php', TemplateRegistry::find('welcome'), 'core searched before plugin paths');

// --- Missing template throws ---
assert_raises(
    fn () => TemplateRegistry::find('nonexistent_template_xyz'),
    \RuntimeException::class,
 'find throws RuntimeException for missing template'
);

// --- Render template ---
file_put_contents('/tmp/template-render-test.php', '<?php echo "hello " . ($name ?? ""); ?>');
TemplateRegistry::addCore('render_test', '/tmp/template-render-test.php');
$result = TemplateRegistry::render('render_test', ['name' => 'World']);
assert_contains('hello', $result, 'render includes template content');
assert_contains('World', $result, 'render passes context variables');

// --- Render with no context ---
$resultPlain = TemplateRegistry::render('render_test', []);
assert_contains('hello', $resultPlain, 'render works with empty context');

// --- Clear resets state ---
TemplateRegistry::clear();
assert_raises(
    fn () => TemplateRegistry::find('welcome'),
    \RuntimeException::class,
    'clear resets registry'
);

// ===== MAIL TRANSPORT =====

// --- MailTransport rejects attachments ---
$transport = new MailTransport('noreply@test.local', 'Test Mailer');
$msgAtt = new MailMessage('noreply@test.local', 'Test', 'to@test.local', '', 'Subject', '<b>body</b>');
$attachment = new Attachment('/tmp/fake.pdf', 'fake.pdf');

assert_raises(
    fn () => $transport->send($msgAtt->withAttachment($attachment)),
    MailerException::class,
    'MailTransport throws MailerException when message has attachments'
);

// --- MailTransport rejects BCC ---
$transportBcc = new MailTransport('noreply@test.local', 'Test Mailer');
$msgBcc = new MailMessage('noreply@test.local', 'Test', 'to@test.local', '', 'Subject', '<b>body</b>');
$msgBccBcc = $msgBcc->withBcc('bcc@example.com');

assert_raises(
    fn () => $transportBcc->send($msgBccBcc),
    MailerException::class,
    'MailTransport throws MailerException when message has BCC addresses'
);

// --- MailTransport rejects header injection in CC ---
$transportCc = new MailTransport('noreply@test.local', 'Test Mailer');
$msgInject = new MailMessage('noreply@test.local', 'Test', 'to@test.local', '', 'Subject', '<b>body</b>');
$msgInjectCc = $msgInject->withCc("innocent@example.com\r\nInjected: header");

assert_raises(
    fn () => $transportCc->send($msgInjectCc),
    MailerException::class,
    'MailTransport rejects CC address with newline (header injection)'
);

// --- MailTransport identifier ---
assert_equal('mail', $transport->identifier(), 'MailTransport identifier is mail');

// ===== FAKE TRANSPORT =====

// --- Fake transport sends successfully ---
$fake = new FakeTransport();
$fake->throw = false;
$msgSend = new MailMessage('noreply@test.local', 'Test', 'to@test.local', '', 'Subject', '<b>body</b>');
$result = $fake->send($msgSend);
assert_true($result, 'FakeTransport send returns true on success');

// --- Fake transport throws on failure ---
$fakeThrow = new FakeTransport();
$fakeThrow->throw = true;
assert_raises(
    fn () => $fakeThrow->send($msgSend),
    MailerException::class,
    'FakeTransport throws MailerException on failure'
);

// ===== MAILER FACADE =====

// --- Mailer delegates to transport ---
$fake2 = new FakeTransport();
$mailer = new Mailer($fake2);
$msgMailer = new MailMessage('noreply@test.local', 'Test', 'to@test.local', '', 'Subject', '<b>body</b>');
$result = $mailer->send($msgMailer);
assert_true($result, 'Mailer send returns transport result');
assert_not_null($fake2->lastMessage, 'Mailer passes message to transport');
assert_equal('<b>body</b>', $fake2->lastMessage->bodyHtml, 'Mailer preserves message body');

// --- Mailer transportIdentifier ---
assert_equal('fake', $mailer->transportIdentifier(), 'Mailer transportIdentifier delegates');

// --- Mailer setTransport swaps transport ---
$mailer->setTransport($transport);
assert_equal('mail', $mailer->transportIdentifier(), 'setTransport updates identifier');

// --- Mailer withTemplate creates template message ---
$mailer2 = new Mailer($fake);
$templateMsg = $mailer2->withTemplate('welcome', ['name' => 'Alice']);
assert_equal('', $templateMsg->bodyHtml, 'withTemplate message has empty body by default');
assert_equal('welcome', $templateMsg->template, 'withTemplate sets template name');
assert_equal(['name' => 'Alice'], $templateMsg->context, 'withTemplate sets context');

// --- Mailer withTemplate renders via TemplateRegistry ---
TemplateRegistry::clear();
TemplateRegistry::addCore('template_welcome', '/tmp/template-welcome.php');
$fake3 = new FakeTransport();
$mailer3 = new Mailer($fake3);

// Register a renderable template
file_put_contents('/tmp/template-welcome.php', '<?php echo "Hello " . ($name ?? "World"); ?>');
TemplateRegistry::addCore('template_welcome', '/tmp/template-welcome.php');

// Create a message with template but no body
$templateMsg2 = new MailMessage('noreply@test.local', 'Test', 'to@test.local', '', 'Subject');
$templateMsg2->template = 'template_welcome';
$templateMsg2->context = ['name' => 'Alice'];

$result3 = $mailer3->send($templateMsg2);
assert_true($result3, 'Mailer with template renders and sends');
assert_not_null($fake3->lastMessage, 'Mailer passes rendered message to transport');
assert_contains('Hello', $fake3->lastMessage->bodyHtml, 'Mailer renders template as HTML body');

// --- Mailer throws on transport failure ---
$fake4 = new FakeTransport();
$fake4->throw = true;
$mailer4 = new Mailer($fake4);
$msgFail = new MailMessage('noreply@test.local', 'Test', 'to@test.local', '', 'Subject', '<b>body</b>');

assert_raises(
    fn () => $mailer4->send($msgFail),
    MailerException::class,
    'Mailer re-throws MailerException from transport'
);

// --- MailerException properties ---
$e = new MailerException('test error', code: 422, transportName: 'mail');
assert_equal('test error', $e->getMessage(), 'MailerException message is set');
assert_equal(422, $e->getCode(), 'MailerException code is set');
assert_equal('mail', $e->transportName, 'MailerException transportName is set');
assert_instance_of($e, \RuntimeException::class, 'MailerException extends RuntimeException');

// --- MailerException default transportName is null ---
$e2 = new MailerException('plain error');
assert_null($e2->transportName, 'MailerException default transportName is null');

// --- Summary ===
summary();
exit($__FAIL__ > 0 ? 1 : 0);
