<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class OTPService {
    public function generate($user) {
        $code = rand(100000, 999999);
        Cache::put("otp_{$user->id}", $code, now()->addMinutes(10));
        return $code;
    }

    public function verify($user, $code) {
        return Cache::get("otp_{$user->id}") == $code;
    }
}
