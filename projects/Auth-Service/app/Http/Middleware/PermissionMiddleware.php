<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $permission): Response
    {
        $permissions = $request->auth_permissions;

        if (!in_array($permission, $permissions)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}
