<?php

namespace App\Http\Middleware;

use App\Domains\Auth\Service\AuthServiceClient;
use Closure;

class AuthUserMiddleware
{
  public function handle($request, Closure $next)
  {

    $token = $request->bearerToken();

    if (!$token) {
      return response()->json([
        'message' => 'Unauthorized'
      ], 401);
    }

    $authClient = app(AuthServiceClient::class);

    $user = $authClient->getUserFromToken($token);

    $request->attributes->set('auth_user', $user);

    return $next($request);
  }
}



// // how to use this middelware
// $user = request()->attributes->get('auth_user');

// $userId = $user['id'];
// $userName = $user['name'];