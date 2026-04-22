<?php

namespace   App\Domains\CMS\Actions\Project;

use App\Domains\CMS\DTOs\Project\UpdateProjectDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Request;

class UpdateProjectAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'project.update';
  }

  public function __construct(
    private ProjectRepositoryInterface $repository
  ) {}

  public function execute(Project $project, UpdateProjectDTO $dto): Project
  {
    return $this->run(function () use ($dto, $project) {
      event(new SystemLogEvent(
        module: 'cms',
<<<<<<< HEAD
        eventType: 'audit',
        userId: Request()->attributes->get('auth_user')->id ?? 3,
=======
        eventType: 'project_updated',
        userId:$project->owner_id,
>>>>>>> 3281b57fe309f120693e70fedad5e2094b119700
        entityType: 'project',
        entityId: $project->id
      ));

      $updated = $this->repository->update(
        $project,
        $dto->toArray()
      );

      Cache::forget(CacheKeys::project($project->id));
      Cache::forget(CacheKeys::allProjects());
      return $updated;
    });
  }
}
