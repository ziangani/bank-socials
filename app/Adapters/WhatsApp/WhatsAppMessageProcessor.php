<?php

namespace App\Adapters\WhatsApp;

use App\Models\ProcessedMessages;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageProcessor
{
    protected string $channel = 'whatsapp';

    public function isMessageProcessed(string $messageId): bool
    {
        return ProcessedMessages::where('message_id', $messageId)
            ->where('driver', $this->channel)
            ->exists();
    }

    public function markMessageAsProcessed(string $messageId): bool
    {
        try {
            $processedMessage = new ProcessedMessages();
            $processedMessage->message_id = $messageId;
            $processedMessage->driver = $this->channel;
            $processedMessage->save();

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp message processing error: ' . $e->getMessage());
            return false;
        }
    }

    public function getUserIdentifier(array $request): string
    {
        // Check if request is in new format
        if (isset($request['sender'])) {
            return $request['sender'];
        }

        // Legacy format
        $entry = $request['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $message = $value['messages'][0] ?? null;

        return $message['from'] ?? '';
    }

    public function getMessageType(array $request): string
    {
        // Check if request is in new format
        if (isset($request['type'])) {
            return $request['type'];
        }

        // Legacy format
        $entry = $request['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $message = $value['messages'][0] ?? null;

        return $message['type'] ?? 'text';
    }
}
