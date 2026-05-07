<?php

namespace App\Http\Middleware;

use App\Models\Domains\Notifications\Models\NotificationServiceClient;
use App\Services\Auth\AuthApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthServiceMiddleware
{
    protected $auth;

    public function __construct(AuthApiClient $authApiClient)
    {
        $this->auth = $authApiClient;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $service = (object) $this->auth->getServiceFromToken($token);
            $request->attributes->set('auth_service_client', $service);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 401);
        }





        return $next($request);
    }
}
