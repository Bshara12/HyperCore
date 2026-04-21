<?php

namespace App\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class IndexDataCollectionAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.list';
  }

  public function __construct(protected DataCollectionRepositoryInterface $repository, protected ProjectRepositoryInterface $projectRepository) {}

  public function execute($projectKey)
  {
    return $this->run(function () use ($projectKey) {

      $projectId = $this->projectRepository->findByKey($projectKey)->id;

      return Cache::remember(
        CacheKeys::collections($projectId),
        CacheKeys::TTL_MEDIUM,
        fn() => $this->repository->list($projectId)
      );
    });
  }
}
