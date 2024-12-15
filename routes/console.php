<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\CleanupExpiredSessions;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('whatsapp:cleanup-sessions', function () {
    $this->call(CleanupExpiredSessions::class);
})->purpose('Clean up expired WhatsApp sessions');
