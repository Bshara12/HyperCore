<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * يقرأ user_id من الـ request attributes اللي حطها auth.jwt middleware مسبقاً
     * بدل ما كل controller method يعيد فك تشفير التوكن من جديد
     */
    protected function authUserId(Request $request): ?int
    {
        return $request->attributes->get('auth_user_id');
    }

    protected function authSessionId(Request $request): ?string
    {
        return $request->attributes->get('auth_session_id');
    }
}
