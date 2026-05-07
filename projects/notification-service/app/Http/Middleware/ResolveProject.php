<?php

namespace App\Http\Middleware;

use App\Services\CMS\CMSApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveProject
{
    public function __construct(
        protected CMSApiClient $resolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $projectId = $request->header('X-Project-Id');

        if (! $projectId) {
            return response()->json([
                'message' => 'X-Project-Id header is required',
            ], 400);
        }

        try {
            $project = $this->resolver->resolveProject();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to resolve project',
            ], 422);
        }

        $request->merge([
            'project_id' => $project['id'] ?? $projectId,
            'project' => $project,
        ]);

        $request->attributes->set('project', $project);

        return $next($request);
    }
}
