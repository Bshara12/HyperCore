<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformMiddleware
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
        $token = $request->bearerToken();

        $decoded = $this->jwtService->validateToken($token);
        if($decoded->type !== 'platform') {
            return response()->json([
                'error' => 'Invalid token type!'
            ], 403);
        }

        $request->merge(['auth_user_id' => $decoded->sub]);
        
        return $next($request);
    }
}
