<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Actions\Project\CreateProjectAction;
use App\Domains\CMS\Actions\Project\DeleteProjectAction;
use App\Domains\CMS\Actions\Project\ListProjectsAction;
use App\Domains\CMS\Actions\Project\ShowProjectAction;
use App\Domains\CMS\Actions\Project\UpdateProjectAction;
use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\DTOs\Project\UpdateProjectDTO;
use App\Models\Project;

class ProjectService
{
  public function __construct(
    private CreateProjectAction $createProjectAction,
    private UpdateProjectAction $updateAction,
    private ShowProjectAction $showAction,
    private ListProjectsAction $listAction,
    private DeleteProjectAction $deleteAction
  ) {}

  public function resolve($request)
  {
    $projectKey = $request->header('X-Project-Id');

    if (!$projectKey) {
      return response()->json(['message' => 'Project Id is required'], 400);
    }

    $project = Project::where('public_id', $projectKey)->first();

    if (!$project) {
      return response()->json(['message' => 'Project not found'], 404);
    }

    return response()->json([
      'id' => $project->id,
      'public_id' => $project->public_id,
      'name' => $project->name,
      'owner_id' => $project->owner_id,
      'enabled_modules'=> $project->enabled_modules,
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
  public function list(): \Illuminate\Support\Collection
  {
    return $this->listAction->execute();
  }
  public function delete(Project $project): void
  {
    $this->deleteAction->execute($project);
  }
}
