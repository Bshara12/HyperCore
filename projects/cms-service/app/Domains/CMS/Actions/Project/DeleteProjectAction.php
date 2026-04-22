<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Events\SystemLogEvent;
use App\Models\Project;

class DeleteProjectAction
{
  public function __construct(
    private ProjectRepositoryInterface $repository
  ) {}

  public function execute(Project $project): void
  {
    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'audit',
      userId: $project->owner_id,
      entityType: 'delete project',
      entityId: $project->id,
      oldValues: $project->toArray(),
      newValues: ['dleted']
    ));
    $this->repository->delete($project);
  }
}
