<?php

namespace App\Domains\CMS\Actions\Project;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class ListProjectsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'project.index';
  }

  public function __construct(
    private ProjectRepositoryInterface $repository
  ) {}

  public function execute(): \Illuminate\Support\Collection
  {
    return $this->run(function () {

      return Cache::remember(
        CacheKeys::allProjects(),      // المفتاح
        CacheKeys::TTL_LONG,           // مدة الـ Cache (يوم)
        fn() => $this->repository->all() // لو مش موجود، نجيبه من DB
      );
    });
  }
}
