<?php

namespace App\Common;

use App\Models\Merchants;
use App\Models\User;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class Auth
{
    public static function authenticateAdmin($authId, $authPassword)
    {
        $user = User::where('auth_id', 'ilike', $authId)
            ->where('status', GeneralStatus::STATUS_ACTIVE)
            ->first();

        if (!$user) {
            return false;
        }
        if (!in_array($user->user_class, [UserClasses::USER_CLASS_ADMIN_USER, UserClasses::USER_CLASS_MERCHANT_USER])) {
            return false;
        }

        if (!Hash::check($authPassword, $user->auth_password)) {
            return false;
        }

        try {
            $user->last_login_date = date('Y-m-d H:i:s');
            $user->save();
        } catch (\Exception $e) {
        }

        return $user;
    }

    public static function startAdminSession(User $user)
    {
        session([
            'userId' => $user->id,
            'userUuid' => $user->uuid,
            'merchantId' => $user->merchant_id,
            'userClass' => $user->user_class,
        ]);
        \Illuminate\Support\Facades\Auth::login($user, true);
    }

    public static function user()
    {
        return Users::find(session('userId'));
    }

    public static function endSession(Request $request)
    {
        $request->session()->flush();
    }

    public static function isAdminLoggedIn()
    {
        if (!session()->has('userId')) {
            return false;
        }

        if (self::getUserClass() != UserClasses::USER_CLASS_ADMIN_USER) {
            return false;
        }
        return true;
    }

    public static function getUserClass()
    {
        return session('userClass');
    }

    public static function isMerchantLoggedIn()
    {
        if (!session()->has('userId')) {
            return false;
        }
        if (self::getUserClass() != UserClasses::USER_CLASS_MERCHANT_USER) {
            return false;
        }
        return true;
    }

    public static function merchantId()
    {
        return session('merchantId');
    }
}
