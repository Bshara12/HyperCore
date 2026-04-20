<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = str_replace('Bearer ', '', $header);

        $decoded = $this->jwtService->validateToken($token);

        if (!$decoded) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }
        $sessionId = $decoded->sid ?? null;

        if (!$sessionId) {
            return response()->json(['message' => 'Invalid session'], 401);
        }

        $session = DB::table('my_sessions')
            ->where('id', $sessionId)
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Session not found'], 401);
        }

        if ($session->revoked_at !== null) {
            return response()->json(['message' => 'Session revoked'], 401);
        }

        if (now()->greaterThan($session->expires_at)) {
            return response()->json(['message' => 'Session expired'], 401);
        }
        // يمكنك تمرير user_id داخل request
        // $request->merge(['user_id' => $decoded->sub]);

        // نخزن الـ payload داخل request
        $request->attributes->set('jwt_payload', $decoded);
        //added
        $request->attributes->set('auth_session_id', $sessionId);
        $request->attributes->set('auth_user_id', $decoded->sub);

        return $next($request);
    }
}
