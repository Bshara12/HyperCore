<?php

namespace App\Domains\Platform\Actions;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ListAllProjectsAction
{
    /**
     * Every project on the platform, for the operator dashboard.
     *
     * This is the one place allowed to read across tenants — the route behind
     * it is gated by EnsureHyperCore. The tenant-facing listing
     * (ListProjectsAction) stays scoped to the caller.
     */
    public function execute(
        ?string $search,
        ?string $module,
        ?int $ownerId,
        bool $includeTrashed,
        int $perPage
    ): LengthAwarePaginator {

        return Project::query()
            ->when(
                $includeTrashed,
                fn ($query) => $query->withTrashed()
            )
            ->when(
                $search,
                fn ($query) => $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('public_id', $search);
                })
            )
            ->when(
                $ownerId,
                fn ($query) => $query->where('owner_id', $ownerId)
            )
            ->when(
                $module,
                // enabled_modules is a JSON array of flag strings, so this has
                // to be a JSON containment test, not an equality check.
                fn ($query) => $query->whereJsonContains('enabled_modules', $module)
            )
            ->withCount([
                'dataTypes',
                'entries',
                'subscriptions',
            ])
            ->latest()
            ->paginate($perPage);
    }
}
