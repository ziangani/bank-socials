<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WhatsAppSession;
use App\Integrations\WhatsAppService;
use Carbon\Carbon;

class CleanupExpiredSessions extends Command
{
    protected $signature = 'whatsapp:cleanup-sessions';
    protected $description = 'Clean up expired WhatsApp sessions';

    protected WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        parent::__construct();
        $this->whatsappService = $whatsappService;
    }

    public function handle()
    {
        $this->info('Starting cleanup of expired sessions...');

        $timeout = config('whatsapp.session_timeout', 600);
        $cutoff = Carbon::now()->subSeconds($timeout);
        $businessId = config('whatsapp.business_phone_id');

        WhatsAppSession::where('updated_at', '<', $cutoff)
            ->where('status', 'active')
            ->chunk(100, function ($sessions) {
                foreach ($sessions as $session) {
                    try {
                        // Send expiry notification
                        $this->whatsappService->sendMessage(
                            $session->sender,
                            config('whatsapp.business_phone_id'),
                            config('whatsapp.session_expiry_message')
                        );

                        // Update session status
                        $session->update(['status' => 'expired']);

                        $this->info("Expired session {$session->session_id} for {$session->sender}");
                    } catch (\Exception $e) {
                        $this->error("Failed to process session {$session->session_id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info('Session cleanup completed');
    }
}
