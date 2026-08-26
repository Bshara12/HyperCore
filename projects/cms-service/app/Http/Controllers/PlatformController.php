<?php

namespace App\Http\Controllers;

use App\Domains\Platform\Requests\ListAllProjectsRequest;
use App\Domains\Platform\Requests\ListPlatformLogsRequest;
use App\Domains\Platform\Services\PlatformService;

/**
 * Platform-operator surface. Every route reaching this controller is gated by
 * the `hypercore` middleware, which is what makes reading across every tenant
 * legitimate here.
 */
class PlatformController extends Controller
{
    public function __construct(
        private PlatformService $service
    ) {}

    public function overview()
    {
        return response()->json([
            'data' => $this->service->overview(),
        ]);
    }

    public function health()
    {
        $health = $this->service->health();

        return response()->json([
            'data' => $health,
        ]);
    }

    public function projects(ListAllProjectsRequest $request)
    {
        $projects = $this->service->projects(
            search: $request->search,
            module: $request->module,
            ownerId: $request->owner_id
                ? (int) $request->owner_id
                : null,
            includeTrashed: $request->boolean('include_trashed'),
            perPage: (int) ($request->per_page ?? 25)
        );

        return response()->json([
            'data' => $projects->items(),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Logs are proxied from the Logging Service rather than exposed to the
    | browser: that service has no authentication of its own.
    |--------------------------------------------------------------------------
    */

    public function logs(ListPlatformLogsRequest $request)
    {
        return response()->json(
            $this->service->logs($request->forwardableFilters())
        );
    }

    public function auditLogs()
    {
        return response()->json(
            $this->service->auditLogs()
        );
    }
}
