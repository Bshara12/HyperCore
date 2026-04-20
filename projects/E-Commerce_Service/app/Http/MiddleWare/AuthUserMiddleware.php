<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthApiClient;
use Closure;

class AuthUserMiddleware
{

  public function __construct(
    protected AuthApiClient $authClient
  ) {}

  public function handle($request, Closure $next)
  {
    $token = $request->bearerToken();

    if (!$token) {
      return response()->json([
        'message' => 'Unauthorized'
      ], 401);
    }

    $user = $this->authClient->getUserFromToken($token);

    $request->attributes->set('auth_user', $user);

    return $next($request);
  }
}
