<?php

namespace Plugins\Smtp;

use App\Core\Controller;
use App\Core\Mail\MailMessage;
use App\Core\Mail\MailerException;

/**
 * Test-email endpoint for SMTP configuration verification.
 */
class SmtpMailerController extends Controller
{
    public function testEmail(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user = $principal['user'] ?? null;

        if ($user === null) {
            $this->json(['error' => 'Authentication required'], 401);
            return;
        }

        if (!in_array('settings.smtp', $principal['permissions'] ?? [], true)) {
            $this->json(['error' => 'Permission denied'], 403);
            return;
        }

        $to = $_POST['to'] ?? '';
        if ($to === '') {
            $to = $user['email'] ?? '';
        }

        if ($to === '') {
            $this->json(['error' => 'No recipient address available. Provide "to" or ensure user has an email.'], 400);
            return;
        }

        $mailCfg = $this->container->get('config')['mail'] ?? [];
        $fromAddress = $mailCfg['from_address'] ?? 'noreply@localhost';
        $fromName = $mailCfg['from_name'] ?? 'Kernel-Web';

        $message = new MailMessage(
            from: $fromAddress,
            fromName: $fromName,
            to: $to,
            toName: $user['display_name'] ?? 'User',
            subject: 'Kernel-Web SMTP Test',
            bodyHtml: '<p>This is a test email from Kernel-Web. If you received this, SMTP is configured correctly.</p>',
        );

        try {
            $mailer = $this->container->get('mailer');
            $mailer->send($message);
            $this->json([
                'success' => true,
                'message' => 'Test email sent to ' . htmlspecialchars($to),
            ]);
        } catch (MailerException $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'transport' => $e->transportName ?? 'unknown',
            ], 500);
        }
    }
}
