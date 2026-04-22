<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

class ShowProjectAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'project.show';
  }

  public function __construct(
    private ProjectRepositoryInterface $repository
  ) {}

  public function execute(Project $project): Project
  {
    return $this->run(function () use ($project) {
      return Cache::remember(
        CacheKeys::project($project->id),
        CacheKeys::TTL_LONG,
        fn() => $this->repository->find($project)
      );
    });
  }
}
