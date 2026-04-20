<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Missing token'], 401);
        }

        $publicKey = file_get_contents(storage_path('keys/auth_public.pem'));

        try {

            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

        } catch (Exception $e) {

            return response()->json(['error' => 'Invalid token'], 401);
        }

        $request->merge([
            'auth_user_id' => $decoded->sub,
            'auth_project_id' => $decoded->proj,
            'auth_role' => $decoded->role
        ]);

        return $next($request);
    }


}
