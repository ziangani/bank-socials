<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use Illuminate\Support\Facades\Config;

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
        $menuButtons = [];
        foreach ($menu as $key => $item) {
            $menuButtons[$key] = $item['text'];
        }

        return [
            'message' => $message,
            'type' => 'interactive',
            'buttons' => $menuButtons,
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
