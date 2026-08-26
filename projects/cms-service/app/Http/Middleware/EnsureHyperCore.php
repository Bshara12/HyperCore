<?php

namespace App\Http\Middleware;

use App\Support\ActingUser;
use Closure;
use Illuminate\Http\Request;

/**
 * Gate for the platform-operator surface.
 *
 * Runs after AuthUserMiddleware, which puts the Auth Service profile — roles
 * included — on the request. The Auth Service only lets an existing hyper_core
 * grant that role, so holding it is proof of platform ownership.
 */
class EnsureHyperCore
{
    public function handle(Request $request, Closure $next)
    {
        if (! ActingUser::isHyperCore($request)) {

            return response()->json([
                'message' => 'This endpoint is restricted to platform operators.',
            ], 403);
        }

        return $next($request);
    }
}
