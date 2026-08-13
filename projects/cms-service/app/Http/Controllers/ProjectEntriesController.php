<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Read\Services\EntryReadService;
use App\Domains\CMS\Requests\ProjectEntriesRequest;
// ها جديد
use App\Models\Project;
class ProjectEntriesController extends Controller
{
    public function __construct(
        private EntryReadService $service
    ) {}
// 👈 2. استبدال int $projectId بـ Project $project
    public function index(ProjectEntriesRequest $request, Project $project)
    {
        $result = $this->service->getProjectEntriesTree(
            projectId: $project->id,
            filters: $request->getFilters()
        );

        return response()->json($result);
    }
}
