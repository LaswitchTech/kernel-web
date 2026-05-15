<?php

namespace Plugins\Telico;

use App\Core\MessengerException;
use App\Core\MessengerTransportInterface;
use App\Core\Message;

/**
 * Telico SMS transport — implements MessengerTransportInterface.
 *
 * Reads credentials from a config array (populated by TelicoHooks at bootstrap).
 * Delegates actual API calls to TelicoApiClient.
 */
class TelicoTransport implements MessengerTransportInterface
{
    private TelicoApiClient $client;

    public function __construct(array $config)
    {
        $this->client = new TelicoApiClient($config);
    }

    /**
     * Send a message via Telico SMS API.
     *
     * @throws MessengerException on API failure, missing credentials, or invalid JSON
     */
    public function send(Message $message): bool
    {
        // TelicoApiClient::sendSms throws MessengerException on any failure.
        // We let it propagate so the Messenger service can surface the error.
        $this->client->sendSms($message->to, $message->body);
        return true;
    }

    public function identifier(): string
    {
        return 'telico';
    }
}
