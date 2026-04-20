<?php

namespace   App\Domains\CMS\Actions\Project;

use App\Domains\CMS\DTOs\Project\UpdateProjectDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use App\Models\Project;
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
        eventType: 'audit',
        userId: Request()->attributes->get('auth_user')->id??3,
        entityType: 'project',
        entityId: null,
        oldValues: null,
        newValues: null
      ));
      return $this->repository->update(
        $project,
        $dto->toArray()
      );
    });
  }
}
