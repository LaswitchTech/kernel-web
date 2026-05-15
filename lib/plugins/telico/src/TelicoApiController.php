<?php

namespace Plugins\Telico;

use App\Core\Controller;
use App\Core\MessengerException;
use App\Core\Message;

/**
 * Test-SMS endpoint for Telico configuration verification.
 */
class TelicoApiController extends Controller
{
    /**
     * Send a test SMS via Telico.
     *
     * POST /api/telico/test-sms
     * Body (JSON): {"to": "+15551234567"}  (optional — defaults to user's phone)
     */
    public function testSms(array $params = []): void
    {
        $principal = $this->container->get('principal');
        $user = $principal['user'] ?? null;

        if ($user === null) {
            $this->json(['error' => 'Authentication required'], 401);
            return;
        }

        if (!in_array('settings.telico', $principal['permissions'] ?? [], true)) {
            $this->json(['error' => 'Permission denied'], 403);
            return;
        }

        $to = $_POST['to'] ?? '';
        if ($to === '') {
            $to = $user['phone'] ?? '';
        }

        if ($to === '') {
            $this->json(['error' => 'No recipient phone available. Provide "to" or ensure user has a phone number.'], 400);
            return;
        }

        $messengerCfg = ($this->container->get('config')['messenger'] ?? []);
        $fromNumber   = $messengerCfg['from_number'] ?? '';

        $message = new Message(
            to: $to,
            from: $fromNumber,
            body: 'This is a test SMS from Kernel-Web. If you received this, Telico SMS is configured correctly.',
        );

        try {
            $messenger = $this->container->get('messenger');
            $messenger->send($message);
            $this->json([
                'success' => true,
                'message' => 'Test SMS sent to ' . htmlspecialchars($to),
            ]);
        } catch (MessengerException $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'transport' => $e->transportName ?? 'unknown',
            ], 500);
        }
    }
}
