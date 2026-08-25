<?php

namespace App\Http\Middleware;

use App\Domains\Auth\Repository\Interface\ProjectUserRepositoryInterface;
use App\Support\CurrentProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ties the authenticated user to the resolved project.
 *
 * ResolveProject answers "which project is this request about" and
 * AuthUserMiddleware answers "who is calling", but nothing connected the two:
 * any valid token plus any X-Project-Key returned that project's data. This is
 * the missing link — access means owning the project or being a member of it.
 *
 * Must run after both resolve.project and auth.user.
 */
class EnsureProjectAccess
{
    public function __construct(
        private ProjectUserRepositoryInterface $members
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $project = CurrentProject::resolve($request);

        if (! $project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $user = $request->attributes->get('auth_user');

        $userId = is_array($user)
            ? ($user['id'] ?? null)
            : (is_object($user) ? ($user->id ?? null) : null);

        if (! $userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ((int) $project->owner_id === (int) $userId) {
            return $next($request);
        }

        if ($this->members->exists((int) $userId, (string) $project->public_id)) {
            return $next($request);
        }

        // 404, not 403: a caller with no access should not learn that the
        // project exists.
        return response()->json(['message' => 'Project not found'], 404);
    }
}
