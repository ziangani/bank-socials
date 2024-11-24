<?php

namespace App\Common;

use Illuminate\Support\Facades\Log;

class Logger
{
    const LOG_TYPE_ASSESSMENT = 'assessment';
    const LOG_TYPE_RECEIPTING = 'receipting';
    const LOG_TYPE_CLAIMS = 'claims';
    const LOG_TYPE_EMPLOYER_REGISTRATION = 'employer_registration';

    public static function logEvent($message, $channel)
    {
        Log::channel($channel)->info($message);
    }

    public static function logError($message, $channel)
    {
        Log::channel($channel)->error($message);
    }

}
