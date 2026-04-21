<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreateProjectAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'project.create';
  }

  public function __construct(
    private ProjectRepositoryInterface $repository
  ) {}

  public function execute(CreateProjectDTO $dto): Project
  {

    $data = $dto->toArray();

    $data['public_id'] = Str::uuid()->toString();
    // أو Str::random(32)
    $data['slug'] = Str::slug($data['name']);


    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'audit',
      userId: $dto->ownerId,
      entityType: 'project',
      entityId: null,
      oldValues: null,
      newValues: null
    ));
    $project = $this->repository->create($data);
    Cache::forget(CacheKeys::allProjects());
    return $project;
  }
}
