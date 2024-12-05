<?php

namespace App\Adapters;

use App\Interfaces\MessageAdapterInterface;
use App\Adapters\WhatsApp\WhatsAppMessageParser;
use App\Adapters\WhatsApp\WhatsAppSessionManager;
use App\Adapters\WhatsApp\WhatsAppMessageProcessor;
use App\Adapters\WhatsApp\WhatsAppMessageSender;
use App\Integrations\WhatsAppService;

class WhatsAppMessageAdapter implements MessageAdapterInterface
{
    protected WhatsAppMessageParser $parser;
    protected WhatsAppSessionManager $sessionManager;
    protected WhatsAppMessageProcessor $processor;
    protected WhatsAppMessageSender $sender;
    protected string $channel = 'whatsapp';

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->parser = new WhatsAppMessageParser();
        $this->sessionManager = new WhatsAppSessionManager();
        $this->processor = new WhatsAppMessageProcessor();
        $this->sender = new WhatsAppMessageSender($whatsAppService);
    }

    public function parseIncomingMessage(array $request): array
    {
        return $this->parser->parseIncomingMessage($request);
    }

    public function formatOutgoingMessage(array $response): array
    {
        return $this->parser->formatOutgoingMessage($response);
    }

    public function getMessageContent(array $request): string
    {
        return $this->parser->getMessageContent($request);
    }

    public function getSessionData(string $sessionId): ?array
    {
        return $this->sessionManager->getSessionData($sessionId);
    }

    public function createSession(array $data): string
    {
        return $this->sessionManager->createSession($data);
    }

    public function updateSession(string $sessionId, array $data): bool
    {
        return $this->sessionManager->updateSession($sessionId, $data);
    }

    public function endSession(string $sessionId): bool
    {
        return $this->sessionManager->endSession($sessionId);
    }

    public function isMessageProcessed(string $messageId): bool
    {
        return $this->processor->isMessageProcessed($messageId);
    }

    public function markMessageAsProcessed(string $messageId): bool
    {
        if (config('app.env') == 'local')
            return true;

        return $this->processor->markMessageAsProcessed($messageId);
    }

    public function getUserIdentifier(array $request): string
    {
        return $this->processor->getUserIdentifier($request);
    }

    public function getMessageType(array $request): string
    {
        return $this->processor->getMessageType($request);
    }

    public function formatMenuOptions(array $options): array
    {
        return $this->parser->formatMenuOptions($options);
    }

    public function formatButtons(array $buttons): array
    {
        return $this->parser->formatButtons($buttons);
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        if (config('app.env') == 'local')
            return true;

        return $this->sender->sendMessage($recipient, $message, $options);
    }

    public function markMessageAsRead(string $sender, string $messageId): void
    {
        if (config('app.env') == 'local')
            return;

        $this->sender->markMessageAsRead($sender, $messageId);
    }
}
