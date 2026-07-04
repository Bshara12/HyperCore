<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalApiKey
{
    /**
     * يحمي المسارات المخصصة للتواصل بين الخدمات فقط (Service-to-Service)
     * مستقل كلياً عن نظام مصادقة المستخدمين (Sanctum/JWT/أي شيء آخر)
     *
     * نقارن قيمة Header مخصص X-Internal-Api-Key مع القيمة المخزَّنة في .env
     * هذا يضمن أن أي خطأ أو تغيير مستقبلي في نظام توكنات المستخدمين
     * لن يؤثر إطلاقاً على هذا المسار
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = $request->header('X-Internal-Api-Key');
        $expectedKey = config('services.internal.api_key');

        if (empty($expectedKey)) {
            Log::critical('[VerifyInternalApiKey] INTERNAL_SERVICES_API_KEY غير مضبوط في .env!');

            return response()->json([
                'error' => 'Internal API key not configured on server.',
            ], 500);
        }

        if (empty($providedKey) || ! hash_equals($expectedKey, $providedKey)) {
            Log::warning('[VerifyInternalApiKey] محاولة وصول بمفتاح غير صحيح', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Invalid internal API key.',
            ], 401);
        }

        return $next($request);
    }
}
