<?php

namespace Plugins\Telico;

use App\Core\MessengerException;

/**
 * Telico API client — wraps raw HTTP calls to the Telico SMS/Voice API.
 *
 * Mirrors the existing Telico Helper.php but follows Kernel-Web plugin
 * conventions: standalone class, MessengerException errors, zero dependencies.
 */
class TelicoApiClient
{
    private string $username;
    private string $smsPass;
    private string $callerId;

    public function __construct(array $config)
    {
        $this->username = $config['telico.username'] ?? '';
        $this->smsPass  = $config['telico.sms_pass'] ?? '';
        $this->callerId = $config['telico.callerid'] ?? '';
    }

    /**
     * Send an SMS message via Telico.
     *
     * @return array Decoded JSON response from the Telico API
     * @throws MessengerException on API failure or missing credentials
     */
    public function sendSms(string $destination, string $message): array
    {
        if (empty($this->username) || empty($this->smsPass)) {
            throw new MessengerException(
                'Telico credentials not configured.',
                code: 422,
                transportName: 'telico',
            );
        }

        if (empty($this->callerId)) {
            throw new MessengerException(
                'Telico caller ID not configured.',
                code: 422,
                transportName: 'telico',
            );
        }

        $url = 'https://sms.telico.cloud/api/send_sms?' . http_build_query([
            'source_did'  => $this->callerId,
            'destination' => $destination,
            'message'     => $message,
        ]);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new MessengerException(
                'cURL initialization failed.',
                code: 500,
                transportName: 'telico',
            );
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => "{$this->username}:{$this->smsPass}",
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new MessengerException(
                "Telico SMS API returned HTTP {$httpCode}",
                code: $httpCode,
                transportName: 'telico',
            );
        }

        if ($response === false) {
            throw new MessengerException(
                'Telico SMS API returned empty response.',
                code: 500,
                transportName: 'telico',
            );
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new MessengerException(
                'Invalid JSON from Telico SMS API.',
                code: 502,
                transportName: 'telico',
            );
        }

        return $data;
    }

    /**
     * Get all conversations.
     *
     * @return array Decoded JSON response (array of conversations)
     * @throws MessengerException on API failure
     */
    public function getConversations(): array
    {
        $url = 'https://sms.telico.cloud/api/conversations?' . http_build_query([
            'did' => $this->callerId,
        ]);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new MessengerException(
                'cURL initialization failed.',
                code: 500,
                transportName: 'telico',
            );
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => "{$this->username}:{$this->smsPass}",
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            throw new MessengerException(
                'Telico API returned empty response.',
                code: 500,
                transportName: 'telico',
            );
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Get messages for a specific conversation.
     *
     * @return array Decoded JSON response (array of messages)
     * @throws MessengerException on API failure
     */
    public function getMessages(string $conversationId): array
    {
        $url = 'https://sms.telico.cloud/api/messages?' . http_build_query([
            'conversation_id' => $conversationId,
        ]);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new MessengerException(
                'cURL initialization failed.',
                code: 500,
                transportName: 'telico',
            );
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => "{$this->username}:{$this->smsPass}",
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            throw new MessengerException(
                'Telico API returned empty response.',
                code: 500,
                transportName: 'telico',
            );
        }

        return json_decode($response, true) ?? [];
    }
}
