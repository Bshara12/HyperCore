<?php

namespace App\Http\Controllers;

use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\DTOs\Project\JoinProjectDTO;
use App\Domains\CMS\DTOs\Project\UpdateProjectDTO;
use App\Domains\CMS\Requests\CreateProjectRequest;
use App\Domains\CMS\Requests\JoinProjectRequest;
use App\Domains\CMS\Requests\UpdateProjectRequest;
use App\Domains\CMS\Services\ProjectService;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    protected $service;

    public function __construct(ProjectService $service)
    {
        $this->service = $service;
    }

    public function resolve(Request $request)
    {
        return response()->json($this->service->resolve($request));
    }

    public function store(
        CreateProjectRequest $request,
        ProjectService $service
    ) {

        $dto = CreateProjectDTO::fromRequest($request);

        $project = $service->create($dto);

        return response()->json($project, 201);
    }

    public function update(
        UpdateProjectRequest $request,
        Project $project,
        ProjectService $service
    ) {
        $dto = UpdateProjectDTO::fromRequest($request);

        $updated = $service->update($project, $dto);

        return response()->json($updated);
    }

    public function show(
        Project $project,
        ProjectService $service
    ) {
        $result = $service->show($project);

        return response()->json($result);
    }

    public function index(ProjectService $service)
    {
        $projects = $service->list();

        return response()->json($projects);
    }

    public function destroy(
        Project $project,
        ProjectService $service
    ) {
        $service->delete($project);

        return response()->json([
            'message' => 'Project deleted successfully',
        ], 200);
    }


    public function join(
        JoinProjectRequest $request,
        Project $project,
        ProjectService $service
    ) {
        $dto = JoinProjectDTO::fromRequest($request, $project);

        $result = $service->join($dto);

        return response()->json($result, 200);
    }

    public function members(Project $project, ProjectService $service)
    {
        $members = $service->members($project);

        return response()->json(['data' => $members]);
    }

    public function leave(Request $request, Project $project, ProjectService $service)
    {
        // نفس النمط المستخدم في OrderController لجلب هوية المستخدم من التوكن
        $userId = $request->attributes->get('auth_user')['id'];

        $result = $service->leave($project, $userId);

        return response()->json($result);
    }
}
