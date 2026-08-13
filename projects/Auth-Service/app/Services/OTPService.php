<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class OtpService
{
    /**
     * إرسال OTP للمرة الأولى
     */
    public function send(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->update([
                'otp_code'        => random_int(100000, 999999),
                'otp_expires_at'  => now()->addMinutes(10),
                'failed_attempts' => 0,
            ]);

            return $user;
        });
    }

    /**
     * إعادة إرسال OTP عند طلب المستخدم
     */
    public function resend(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->update([
                'otp_code'       => random_int(100000, 999999),
                'otp_expires_at' => now()->addMinutes(10),
            ]);

            return $user;
        });
    }
}
