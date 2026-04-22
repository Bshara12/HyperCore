<?php

namespace App\Http\Middleware;

use Closure;

class CheckPermission
{
  public function handle($request, Closure $next, $permission)
  {
    $user = $request->attributes->get('auth_user');

    if (!in_array($permission, $user['permissions'])) {
      return response()->json([
        'message' => 'Forbidden'
      ], 403);
    }

    return $next($request);
  }
}
