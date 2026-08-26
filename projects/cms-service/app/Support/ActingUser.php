<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Reads the caller identity that AuthUserMiddleware put on the request.
 *
 * The payload is the Auth Service's /my-profile response, so it carries
 * `roles` (each with a `name` and the pivot's `project_id`) plus the
 * flattened `permissions` list AuthServiceClient derives from them.
 *
 * Sibling of CurrentProject: one place that knows the shape, instead of
 * `$request->attributes->get('auth_user')['id']` spread across the codebase.
 */
class ActingUser
{
    public const ROLE_HYPER_CORE = 'hyper_core';

    /**
     * @return array<string, mixed>|null
     */
    public static function get(?Request $request = null): ?array
    {
        $request ??= request();

        $user = $request->attributes->get('auth_user');

        return is_array($user) ? $user : null;
    }

    public static function id(?Request $request = null): ?int
    {
        $id = self::get($request)['id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    /**
     * Every role name the caller holds, in any project.
     *
     * @return string[]
     */
    public static function roles(?Request $request = null): array
    {
        $roles = self::get($request)['roles'] ?? [];

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($role) => is_array($role) ? ($role['name'] ?? null) : null,
            $roles
        )));
    }

    /**
     * Project ids the caller holds any role in.
     *
     * The Auth Service scopes a role assignment per project via the
     * role_user.project_id pivot, so /my-profile already carries the caller's
     * project memberships — no extra call needed. Platform-wide roles have a
     * null project_id and are skipped here.
     *
     * @return int[]
     */
    public static function roleProjectIds(?Request $request = null): array
    {
        $roles = self::get($request)['roles'] ?? [];

        if (! is_array($roles)) {
            return [];
        }

        $ids = [];

        foreach ($roles as $role) {

            if (! is_array($role)) {
                continue;
            }

            // Laravel nests withPivot() columns under `pivot`; tolerate a
            // flattened shape too so a change in the Auth payload cannot
            // silently narrow the scope to nothing.
            $projectId = $role['pivot']['project_id']
                ?? $role['project_id']
                ?? null;

            if ($projectId !== null) {
                $ids[] = (int) $projectId;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function hasRole(
        string $role,
        ?Request $request = null
    ): bool {

        return in_array($role, self::roles($request), true);
    }

    /**
     * Platform operator — the Auth Service seeds `hyper_core` with
     * project_id = null and refuses to let anyone but another hyper_core
     * assign it, so it is the platform-wide superuser.
     */
    public static function isHyperCore(?Request $request = null): bool
    {
        return self::hasRole(self::ROLE_HYPER_CORE, $request);
    }
}
