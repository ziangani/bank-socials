<?php

namespace App\Interfaces;

interface MessageAdapterInterface
{
    /**
     * Parse incoming message from channel
     */
    public function parseIncomingMessage(array $request): array;

    /**
     * Format outgoing message for channel
     */
    public function formatOutgoingMessage(array $response): array;

    /**
     * Get session data
     */
    public function getSessionData(string $sessionId): ?array;

    /**
     * Create new session
     */
    public function createSession(array $data): string;

    /**
     * Update session
     */
    public function updateSession(string $sessionId, array $data): bool;

    /**
     * End session
     */
    public function endSession(string $sessionId): bool;

    /**
     * Check if message has been processed
     */
    public function isMessageProcessed(string $messageId): bool;

    /**
     * Mark message as processed
     */
    public function markMessageAsProcessed(string $messageId): bool;

    /**
     * Get user identifier (phone number, etc.)
     */
    public function getUserIdentifier(array $request): string;

    /**
     * Get message content
     */
    public function getMessageContent(array $request): string;

    /**
     * Get message type (text, interactive, etc.)
     */
    public function getMessageType(array $request): string;

    /**
     * Format menu options
     */
    public function formatMenuOptions(array $options): array;

    /**
     * Format buttons
     */
    public function formatButtons(array $buttons): array;

    /**
     * Send message to user
     */
    public function sendMessage(string $recipient, string $message, array $options = []): bool;
}
