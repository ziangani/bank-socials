<?php

namespace App\Common;

use App\Models\ApiLogs;
use App\Models\BotUserFud;
use App\Models\Emails;
use App\Models\PerformanceLogs;
use App\Models\SmsNotifications;
use App\Models\User;
use App\Models\WhatsAppSessions;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class Helpers
{

    public static function logApiRequest($request, $response, $request_time, $response_time, $entity_state, $new_state, $reference, $source_reference, $request_status, $request_type): void
    {
        try {
            $log = new ApiLogs();
            $log->request = json_encode($request);
            $log->response = json_encode($response);
            $log->request_time = $request_time;
            $log->response_time = $response_time;
            $log->source_ip = request()->ip();
            $log->entity_state = json_encode($entity_state);
            $log->new_state = json_encode($new_state);
            $log->reference = $reference;
            $log->source_reference = $source_reference;
            $log->request_status = $request_status;
            $log->request_type = $request_type;
            $log->save();
        } catch (\Exception $e) {
            Log::channel('api_log')->error('something went wrong: ' . $e->getMessage());
//            throw new \Exception('something went wrong: ' . $e->getMessage());
        }
    }

    public static function generateUUID()
    {
        // Generate a random 16-byte binary string
        $data = random_bytes(16);

        // Set the version (4) and variant (10) bits
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80); // Variant 10

        // Convert binary to a hexadecimal string
        $uuid = bin2hex($data);

        // Format the UUID as per the standard (8-4-4-12)
        $formatted_uuid = substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20, 12);

        return $formatted_uuid;
    }

    public static function createBasicLog($channel, $message, $reference)
    {
        try {
            Log::channel($channel)->info($reference . ' | ' . $message);
        } catch (\Exception $e) {

        }

    }

    public static function getNetwork(mixed $mobile)
    {
        if (strlen($mobile) == 10) {
            $prefix = substr($mobile, 0, 3);
            if (in_array($prefix, ['096', '076'])) {
                return 'MTN';
            } elseif (in_array($prefix, ['097', '077'])) {
                return 'AIRTEL';
            } elseif (in_array($prefix, ['095', '075'])) {
                return 'ZAMTEL';
            } else {
                return 'UNKNOWN';
            }
        } else {
            return 'UNKNOWN';
        }
    }

    public static function generateUUIDV4()
    {

        $str = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        return substr($str, 0, 30);
    }

    public static function createApiKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public static function diffInSeconds($from, $to): string
    {
        return date_diff(DateTime::createFromFormat('Y-m-d H:i:s', $from), DateTime::createFromFormat('Y-m-d H:i:s', $to))->format('%s');
    }

    public static function formatMenu(array $menu): string
    {
        $formatted_menu = '';
        foreach ($menu as $key => $value) {
            $formatted_menu .= trim($key . '. ' . $value['text']) . "\n";
        }
        return $formatted_menu;
    }

    public static function logSession(string $session_id, string $msisdn, string $function, string $action, $session_data, string $request_reference, $sender, $request)
    {
        $session = new WhatsAppSessions();
        $session->session_id = $session_id;
        $session->mobile = $msisdn;
        $session->function = $function;
        $session->action = $action;
        $session->session_data = json_encode($session_data);
        $session->request_reference = $request_reference;
        $session->sender = $sender;
        $session->request = json_encode($request);
        $session->save();
        return $session;
    }

    public static function getLastSession(mixed $session_id, mixed $msisdn)
    {
        return WhatsAppSessions::where('session_id', $session_id)->where('mobile', $msisdn)->where('status', 'ACTIVE')->orderBy('created_at', 'desc')->first();
    }

    public static function endSession(string $session_id, string $msisdn)
    {
        WhatsAppSessions::where('session_id', $session_id)->where('mobile', $msisdn)->where('status', 'ACTIVE')
            ->update(
                [
                    'status' => 'INVALIDATED'
                ]
            );
    }

    public static function getGreetingSalutation(): string
    {
        //say Good morning, Good afternoon, Good evening
        $hour = date('H');
        if ($hour < 12) {
            return 'Good morning';
        } elseif ($hour < 17) {
            return 'Good afternoon';
        } else {
            return 'Good evening';
        }
    }

    public static function getByeSalutation()
    {
        //say Enjoy the rest of your day, Have a great day, Good evening, Good night
       $hour = date('H');
        if ($hour < 12) {
            return 'Enjoy the rest of your day';
        } elseif ($hour < 17) {
            return 'Have a great day';
        } elseif ($hour < 20) {
            return 'Good evening';
        } else {
            return 'Good night';
        }
    }

    public static function logBotUserFud($userId, $friendlyValue, $systemValue, $source, $type, $module)
    {
        try {
            $fud = new BotUserFud();
            $fud->user_id = $userId;
            $fud->system_value = $systemValue;
            $fud->friendly_value = $friendlyValue;
            $fud->source = $source;
            $fud->module = $module;
            $fud->type = $type;
            $fud->save();
        } catch (\Exception $e) {
            throw new \Exception('Something went wrong: ' . $e->getMessage());
        }
    }

    public static function getBotFud($userId, $source, $module, $type)
    {
        try {
            return

                DB::table('bot_user_fuds')
                    ->select('system_value', 'friendly_value')
                    ->where('user_id', $userId)
                    ->where('source', $source)
                    ->where('module', $module)
                    ->where('type', $type)
                    ->distinct()
                    ->limit(3)
                    ->pluck('system_value', 'friendly_value')
                    ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getUserIp()
    {
        return request()->ip();
    }

    public static function LogPerformance($type, $action, $description, $source_id, $source_type, $user_ip, $user_agent, $reference_1 = null, $reference_2 = null, $reference_3 = null, $details = null): void
    {
        try {
            //set session id
            $session_id = session()->getId();
            $log = new PerformanceLogs();
            $log->type = $type;
            $log->action = $action;
            $log->description = $description;
            $log->source_id = $source_id;
            $log->source_type = $source_type;
            $log->reference_1 = $reference_1;
            $log->reference_2 = $reference_2;
            $log->reference_3 = $reference_3;
            $log->user_ip = $user_ip;
            $log->user_agent = $user_agent;
            $log->session_id = $session_id;
            $log->details = json_encode($details);
            $log->save();
        } catch (\Exception $e) {
        }
    }

    public static function isValidZambianMobileNumber($mobile)
    {
        $zambian_mobile_regex = '/^(?:\+?26)?0[97][567]\d{7}$/';
        return preg_match($zambian_mobile_regex, $mobile);
    }

    public static function timeAgo($timestamp)
    {
        $current_time = time();
        $time_diff = $current_time - strtotime($timestamp);

        $seconds = $time_diff;
        $minutes = $seconds / 60;
        $hours = $minutes / 60;
        $days = $hours / 24;
        $weeks = $days / 7;
        $months = $days / 30;
        $years = $days / 365;

        if ($seconds < 60) {
            return $seconds . " secs ago";
        } elseif ($minutes < 60) {
            return round($minutes) . " minutes ago";
        } elseif ($hours < 24) {
            return round($hours) . " hours ago";
        } elseif ($days < 7) {
            return round($days) . " days ago";
        } elseif ($weeks < 4) {
            return round($weeks) . " weeks ago";
        } elseif ($months < 12) {
            return round($months) . " months ago";
        } else {
            return round($years) . " years ago";
        }
    }

    public static function determineMobileNetwork($mobileNumber)
    {
        if (str_starts_with($mobileNumber, '096') || str_starts_with($mobileNumber, '076')) {
            return 'MTN';
        } elseif (str_starts_with($mobileNumber, '097') || str_starts_with($mobileNumber, '077')) {
            return 'Airtel';
        } elseif (str_starts_with($mobileNumber, '095') || str_starts_with($mobileNumber, '075')) {
            return 'Zamtel';
        } else {
            return 'Unknown';
        }
    }

    public static function getSenderId()
    {
        return 'TechPay';
    }

    public static function generatePassword(int $length, $passcode = false)
    {
        $chars = '23456789abcdefghkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        if ($passcode)
            $chars = '23456789';

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    public static function resetUserPassword(User $user)
    {

        $password = Helpers::generatePassword(6, true);

        $first_name = $user->first_name;
        $last_name = $user->surname;

        $user->password = Hash::make($password);
        $user->save();

        $app_name = config('app.name');

        $sms_text = "Dear $first_name $last_name,\n"
            . "Welcome to the {$app_name}\nFind below your login details\n"
            . "Username: {$user->email}\n"
            . "Password: {$password}\n"
            . "URL: " . url('/tpadmin') . "\n"
            . "Thank you.";

        $sms = new SmsNotifications();
        $sms->message = $sms_text;
        $sms->mobile = $user->mobile;
        $sms->status = GeneralStatus::STATUS_PENDING;
        $sms->sender = self::getSenderId();
        $sms->save();

        $data = [
            'name' => $first_name,
            'password' => $password,
            'auth_id' => $user->email,
            'url' => url('/tpadmin')
        ];
        $email = new Emails();
        $email->subject = $app_name .' Login';
        $email->from = config('mail.from.address');
        $email->email = $user->email;
        $email->message = view('emails.login', $data)->render();
        $email->view = 'emails.login';
        $email->data = json_encode($data);
        $email->save();
    }

    public static function getAppShortName()
    {
        return config('app.short_name');
    }

    public static function getAppName()
    {
        return config('app.name');
    }

}
