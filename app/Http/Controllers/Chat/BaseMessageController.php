<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class BaseMessageController extends Controller
{
    protected MessageAdapterInterface $messageAdapter;
    protected SessionManager $sessionManager;

    public function __construct(MessageAdapterInterface $messageAdapter, SessionManager $sessionManager)
    {
        $this->messageAdapter = $messageAdapter;
        $this->sessionManager = $sessionManager;
    }

    protected function getMenuConfig(string $menuKey): array
    {
        return Config::get("menus.{$menuKey}", []);
    }

    protected function formatMenuResponse(string $message, array $menu, bool $endSession = false): array
    {
        // Extract button texts and IDs if present
        $menuButtons = [];
        foreach ($menu as $key => $item) {
            if (is_array($item)) {
                $menuButtons[] = [
                    'text' => $item['text'] ?? '',
                    'id' => $item['id'] ?? $key
                ];
            } else {
                $menuButtons[] = [
                    'text' => $item,
                    'id' => $key
                ];
            }
        }
        
        return [
            'message' => $message,
            'type' => 'interactive',
            'buttons' => $menuButtons,
            'use_custom_ids' => true, // Enable custom IDs by default
            'end_session' => $endSession
        ];
    }

    protected function formatTextResponse(string $message, bool $endSession = false): array
    {
        return [
            'message' => $message,
            'type' => 'text',
            'end_session' => $endSession
        ];
    }

    protected function handleUnknownState(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Sorry, we encountered an error. Please try again.", true);
    }
}
