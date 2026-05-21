<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Read\Services\EntryReadService;
use App\Domains\CMS\Requests\ProjectEntriesRequest;

class ProjectEntriesController extends Controller
{
    public function __construct(
        private EntryReadService $service
    ) {}

    public function index(ProjectEntriesRequest $request, int $projectId)
    {
        $result = $this->service->getProjectEntriesTree(
            projectId: $projectId,
            filters: $request->getFilters()
        );

        return response()->json($result);
    }
}
