<?php

namespace App\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class ShowDataCollectionDetailsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.showDetails';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository,
    protected ProjectRepositoryInterface $projectRepository
  ) {}

  public function execute(string $projectKey, string $collectionSlug)
  {
    return $this->run(function () use ($projectKey, $collectionSlug) {

      $projectId = $this->projectRepository->findByKey($projectKey)->id;

      return Cache::remember(
        CacheKeys::collection($projectId, $collectionSlug),
        CacheKeys::TTL_MEDIUM,
        function () use ($projectId, $collectionSlug) {
          $collection = $this->repository->find($projectId, $collectionSlug);
          $collection['items'] = $this->repository->getCollectionItems($collection->id);
          return $collection;
        }
      );
    });
  }
}
