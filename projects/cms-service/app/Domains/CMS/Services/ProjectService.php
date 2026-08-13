<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Actions\Project\CreateProjectAction;
use App\Domains\CMS\Actions\Project\DeleteProjectAction;
use App\Domains\CMS\Actions\Project\JoinProjectAction;
use App\Domains\CMS\Actions\Project\LeaveProjectAction;
use App\Domains\CMS\Actions\Project\ListProjectMembersAction;
use App\Domains\CMS\Actions\Project\ListProjectsAction;
use App\Domains\CMS\Actions\Project\ShowProjectAction;
use App\Domains\CMS\Actions\Project\UpdateProjectAction;
use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\DTOs\Project\JoinProjectDTO;
use App\Domains\CMS\DTOs\Project\UpdateProjectDTO;
use App\Models\Project;
use Illuminate\Support\Collection;

class ProjectService
{
    public function __construct(
        private CreateProjectAction $createProjectAction,
        private UpdateProjectAction $updateAction,
        private ShowProjectAction $showAction,
        private ListProjectsAction $listAction,
        private DeleteProjectAction $deleteAction,
        private Project $projectModel,
        // ─── جديد ─────────────────────────────────────────────────────────
        private JoinProjectAction $joinAction,
        private ListProjectMembersAction $listMembersAction,
        private LeaveProjectAction $leaveAction
    ) {}

    public function resolve($request)
    {
        $projectKey = $request->header('X-Project-Id');

        if (! $projectKey) {
            return response()->json(['message' => 'Project Id is required'], 400);
        }

        $project = $this->projectModel->where('public_id', $projectKey)->first();
        // $project = Project::where('public_id', $projectKey)->first();

        if (! $project) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        return response()->json([
            'id' => $project->id,
            'public_id' => $project->public_id,
            'name' => $project->name,
            'owner_id' => $project->owner_id,
            'enabled_modules' => $project->enabled_modules,
        ]);
    }

    public function create(CreateProjectDTO $dto): Project
    {
        return $this->createProjectAction->execute($dto);
    }

    public function update(Project $project, UpdateProjectDTO $dto): Project
    {
        return $this->updateAction->execute($project, $dto);
    }

    public function show(Project $project): Project
    {
        return $this->showAction->execute($project);
    }

    public function list(): Collection
    {
        return $this->listAction->execute();
    }

    public function delete(Project $project): void
    {
        $this->deleteAction->execute($project);
    }


    /**
     * تسجيل/دخول مستخدم ضمن هذا المشروع
     * يُرجع array يحتوي access_token + user + is_new_user
     * (وليس Project model، لأن الناتج بيانات مصادقة وليس كياناً من هذه الخدمة)
     */
    public function join(JoinProjectDTO $dto): array
    {
        return $this->joinAction->execute($dto);
    }

    public function members(Project $project): array
    {
        return $this->listMembersAction->execute($project);
    }

    public function leave(Project $project, int $userId): array
    {
        return $this->leaveAction->execute($project, $userId);
    }
}
