<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ServiceAuthMiddleware
{
    public function handle($request, Closure $next)
    {

        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error'=>'Token missing'],401);
        }

        $publicKey = file_get_contents(storage_path('keys/jwt_public.pem'));

        try {

            $decoded = JWT::decode($token, new Key($publicKey,'RS256'));

        } catch (\Exception $e) {

            return response()->json(['error'=>'Invalid token'],401);
        }

        if ($decoded->type !== 'service') {
            return response()->json(['error'=>'Invalid token type'],403);
        }

        return $next($request);
    }
}
