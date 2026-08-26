<?php

namespace App\Http\Middleware;

use App\Repositories\SessionRepositoryInterface;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    protected $jwtService;

    protected $sessions;

    public function __construct(JwtService $jwtService, SessionRepositoryInterface $sessionRepository)
    {
        $this->jwtService = $jwtService;
        $this->sessions = $sessionRepository;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = str_replace('Bearer ', '', $header);
        $decoded = $this->jwtService->validateToken($token);

        if (! $decoded) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        // ✅ إضافة تشديد أمني: هذا middleware مخصص لتوكن مستخدم (platform) فقط
        // سابقاً كان الاعتماد بالصدفة على عدم تطابق sid مع my_sessions لرفض توكنات service/refresh
        // هلق الرفض صريح ومقصود
        if (($decoded->type ?? null) !== 'platform') {
            return response()->json(['message' => 'Invalid token type'], 401);
        }

        $sessionId = $decoded->sid ?? null;

        if (! $sessionId) {
            return response()->json(['message' => 'Invalid session'], 401);
        }

        $session = $this->sessions->findActiveUserSession($sessionId);

        if (! $session) {
            return response()->json(['message' => 'Session not found'], 401);
        }

        if ($session->revoked_at !== null) {
            return response()->json(['message' => 'Session revoked'], 401);
        }

        if (now()->greaterThan($session->expires_at)) {
            return response()->json(['message' => 'Session expired'], 401);
        }

        $request->attributes->set('jwt_payload', $decoded);
        $request->attributes->set('auth_session_id', $sessionId);
        $request->attributes->set('auth_user_id', $decoded->sub);

        return $next($request);
    }
}
