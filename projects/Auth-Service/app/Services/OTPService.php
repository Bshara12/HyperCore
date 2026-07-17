<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class OtpService
{
    /**
     * إرسال OTP للمرة الأولى
     *
     * إذا كان المستخدم مُنشَأً حديثاً وotp_code موجود بالفعل منذ created،
     * فهذا الميثود لن يُستدعى غالباً في التسجيل.
     * لكن قد يُستخدم في حالات مثل: نسيان كلمة المرور (otp_code كان null)
     *
     * → UserObserver::updated() يلتقطه ويرسل sendOtpNotification
     */
    public function send(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->update([
                'otp_code'        => random_int(100000, 999999),
                'otp_expires_at'  => now()->addMinutes(10),
                'failed_attempts' => 0,
            ]);

            // UserObserver::updated()// سيُطلَق تلقائياً هنا
            // isDirty('otp_code') = true
            // getOriginal('otp_code') = null → sendOtpNotification()
            return $user;
        });
    }

    /**
     * إعادة إرسال OTP عند طلب المستخدم
     *
     * → UserObserver::updated() يلتقطه ويرسل sendResendOtpNotification
     *   لأن getOriginal('otp_code') ليس null (كان موجوداً قبل التحديث)
     */
    public function resend(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->update([
                'otp_code'       => random_int(100000, 999999),
                'otp_expires_at' => now()->addMinutes(10),
            ]);

            // UserObserver::updated() سيُطلَق تلقائياً هنا
            // isDirty('otp_code') = true
            // getOriginal('otp_code') != null → sendResendOtpNotification()
            return $user;
        });
    }

    /**
     * حالة خاصة: التسجيل مع OTP في نفس الوقت
     *
     * → UserObserver::created() يلتقطه:
     *   - يرسل welcome دائماً
     *   - otp_code موجود → يرسل sendOtpNotification أيضاً
     */
    public function createUserWithOtp(array $userData): User
    {
        return DB::transaction(function () use ($userData) {
            // User::create → UserObserver::created() يُطلَق تلقائياً
            return User::create([
                ...$userData,
                'otp_code'       => random_int(100000, 999999),
                'otp_expires_at' => now()->addMinutes(10),
            ]);
        });
    }
}



// namespace App\Services;

// use Illuminate\Support\Facades\Cache;

// class OTPService
// {
//     public function generate($user)
//     {
//         $code = rand(100000, 999999);
//         Cache::put("otp_{$user->id}", $code, now()->addMinutes(10));

//         return $code;
//     }

//     public function verify($user, $code)
//     {
//         return Cache::get("otp_{$user->id}") == $code;
//     }
// }
