<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceAuthMiddleware
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Token missing'], 401);
        }

        // ✅ صار بيستخدم JwtService المركزي بدل file_get_contents + JWT::decode يدوياً
        $decoded = $this->jwtService->validateToken($token);

        if (! $decoded) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        if ($decoded->type !== 'service') {
            return response()->json(['error' => 'Invalid token type'], 403);
        }

        $request->attributes->set('jwt_payload', $decoded);
        $request->attributes->set('auth_service_id', $decoded->sub);

        return $next($request);
    }
}
