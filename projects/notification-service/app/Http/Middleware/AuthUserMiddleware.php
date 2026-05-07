<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthUserMiddleware
{
    public function __construct(
        protected AuthApiClient $authClient
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // $user = $this->authClient->getUserFromToken($token);

        // $request->attributes->set('auth_user', $user);

        try {
            $user = $this->authClient->getUserFromToken($token);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $request->attributes->set('auth_user', $user);

        // مهم للبث: يتيح callbacks الخاصة بالقنوات الوصول إلى user resolver
        $request->setUserResolver(fn () => (object) $user);

        return $next($request);
    }
}
