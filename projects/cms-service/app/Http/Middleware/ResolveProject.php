<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveProject
{
  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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
    $projectKey = $request->header('X-Project-Key');
    $projectId = $request->header('X-Project-Id');

    $identifier = $projectKey ?: $projectId;

    if (!$identifier) {
      abort(400, 'X-Project-Key or X-Project-Id header is required');

    }

    $project = null;

    if (is_numeric($identifier)) {
      $project = Project::find((int) $identifier);
    } else {
      $project = Project::where('public_id', $identifier)
        ->orWhere('slug', $identifier)
        ->first();
    }

    if (!$project) {
      abort(404, 'Project not found');
    }

    // if ($project->owner_id !== auth()->id()) {
    //   abort(403, 'Unauthorized project access');
    // }

    app()->instance('currentProject', $project);

    return $next($request);
  }
}
