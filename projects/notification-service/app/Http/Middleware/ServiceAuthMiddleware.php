<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ServiceAuthMiddleware
{
    public function __construct(
        private readonly AuthApiClient $authClient
    ) {}

    /**
     * يُستخدم هذا الـ Middleware على المسارات التي تُستدعى من خدمات أخرى
     * مثل: Auth Service, E-commerce Service, Booking Service...
     * كل خدمة تُرسل Service Token يتم التحقق منه عبر Auth Service
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        // التأكد من وجود الـ Token في الطلب
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Service token is required.',
            ], 401);
        }

        try {
            // التحقق من الـ Token مع خدمة Auth والحصول على معلومات الخدمة
            $service = $this->authClient->getServiceFromToken($token);

            if (empty($service)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Invalid service token.',
                ], 401);
            }

            // إضافة معلومات الخدمة إلى الـ Request لاستخدامها في الـ Controller
            // مثلاً: معرفة أن الطلب جاء من "ecommerce-service"
            $request->merge(['authenticated_service' => $service]);

        } catch (\Exception $e) {
            Log::warning('ServiceAuth failed', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Could not verify service.',
            ], 401);
        }

        return $next($request);
    }
}
