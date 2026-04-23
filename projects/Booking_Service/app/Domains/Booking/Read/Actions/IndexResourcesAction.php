<?php

namespace App\Domains\Booking\Read\Actions;

use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class IndexResourcesAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'resource.index';
  }

  public function __construct(
    private readonly ResourceRepositoryInterface $repository,
  ) {}

  public function execute(int $projectId)
  {
    return $this->run(function () use ($projectId) {
      return Cache::remember(
        CacheKeys::resources($projectId),
        CacheKeys::TTL_LONG,
        fn() => $this->repository->listByProject($projectId)
      );
    });
  }
}
