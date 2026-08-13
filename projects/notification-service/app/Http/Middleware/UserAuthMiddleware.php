<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class UserAuthMiddleware
{
    public function __construct(
        private readonly AuthApiClient $authClient
    ) {}

    /**
     * يُستخدم هذا الـ Middleware على المسارات التي يصل إليها المستخدم مباشرة
     * مثل: جلب الإشعارات الداخلية، تحديد إشعار كمقروء...
     * المستخدم يُرسل User Token صادر من خدمة Auth
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token is required.',
            ], 401);
        }

        try {
            // التحقق من الـ Token والحصول على بيانات المستخدم من Auth Service
            $user = $this->authClient->getUserFromToken($token);

            if (empty($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Invalid user token.',
                ], 401);
            }

            // إضافة بيانات المستخدم إلى الـ Request لاستخدامها في الـ Controller
            $request->merge(['authenticated_user' => $user]);

        } catch (\Exception $e) {
            Log::warning('UserAuth failed', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Could not verify user.',
            ], 401);
        }

        return $next($request);
    }
}
