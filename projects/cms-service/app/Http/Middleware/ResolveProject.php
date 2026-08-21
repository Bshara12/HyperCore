<?php

namespace App\Http\Middleware;

use App\Support\CurrentProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveProject
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    // public function handle(Request $request, Closure $next)
    // {
    //   $projectId = $request->header('X-Project-Id');

    //   if (!$projectId) {
    //     abort(400, 'X-Project-Id header is required');
    //   }

    //   $project = Project::find($projectId);

    //   if (!$project) {
    //     abort(404, 'Project not found');
    //   }

    //   app()->instance('currentProject', $project);

    //   return $next($request);
    // }
    public function handle(Request $request, Closure $next)
    {
        if (! $request->header('X-Project-Key') && ! $request->header('X-Project-Id')) {
            abort(400, 'X-Project-Key or X-Project-Id header is required');
        }

        $project = CurrentProject::resolve($request);

        if (! $project) {
            abort(404, 'Project not found');
        }

        app()->instance('currentProject', $project);

        return $next($request);
    }
}
