<?php

namespace App\Http\Middleware;

use App\Services\CMS\CMSApiClient;
use Closure;

class ResolveProject
{
  public function __construct(
    protected CMSApiClient $resolver
  ) {}

  public function handle($request, Closure $next)
  {
    $projectKey = $request->header('X-Project-Id');

    if (!$projectKey) {
      abort(400, 'X-Project-Id header is required');
    }

    $project = $this->resolver->resolveProject();

    // $request->merge(['project_id' => $project['id']]);
    $request->merge([
      'project_id' => $project['id'],
      'project' => $project // 🔥 مهم
    ]);
    return $next($request);
  }
}
