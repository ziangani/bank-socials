<?php

namespace App\Interfaces;

interface ChannelInterface
{
    /**
     * Process incoming request from channel
     */
    public function processRequest(array $request): array;

    /**
     * Format response for channel
     */
    public function formatResponse(array $response): array;

    /**
     * Validate channel-specific session
     */
    public function validateSession(string $sessionId): bool;

    /**
     * Initialize new session
     */
    public function initializeSession(array $data): string;

    /**
     * End existing session
     */
    public function endSession(string $sessionId): bool;
}
