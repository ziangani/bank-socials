<?php

namespace App\Interfaces;

interface SessionManagerInterface
{
    /**
     * Create new session
     */
    public function create(array $data): string;

    /**
     * Get session data
     */
    public function get(string $sessionId): ?array;

    /**
     * Update session data
     */
    public function update(string $sessionId, array $data): bool;

    /**
     * Delete session
     */
    public function delete(string $sessionId): bool;

    /**
     * Check if session is valid
     */
    public function isValid(string $sessionId): bool;

    /**
     * Get session state
     */
    public function getState(string $sessionId): ?string;

    /**
     * Update session state
     */
    public function setState(string $sessionId, string $state): bool;

    /**
     * Store temporary data in session
     */
    public function setTemp(string $sessionId, string $key, $value): bool;

    /**
     * Get temporary data from session
     */
    public function getTemp(string $sessionId, string $key);

    /**
     * Clear temporary session data
     */
    public function clearTemp(string $sessionId): bool;
}
