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

  public function execute(int $projectId, array $user)
  {
    return $this->run(function () use ($projectId, $user) {

      $cacheKey = $user['roles'][0]['name'] === 'user'
        ? CacheKeys::resourcesForUser($projectId, $user['id'])
        : CacheKeys::resources($projectId);

      return Cache::remember(
        $cacheKey,
        CacheKeys::TTL_LONG,
        function () use ($projectId, $user) {

          if ($user['roles'][0]['name'] === 'user') {
            return $this->repository->listForUser($projectId, $user['id']);
          }

          return $this->repository->listByProject($projectId);
        }
      );
    });
  }
}
