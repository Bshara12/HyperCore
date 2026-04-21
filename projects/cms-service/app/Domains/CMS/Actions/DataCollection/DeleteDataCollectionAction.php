<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class DeleteDataCollectionAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.delete';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository
  ) {}

  public function execute($collectionSlug)
  {
    $this->run(function () use ($collectionSlug) {

      $collection = $this->repository->getBySlug($collectionSlug);

      $this->repository->delete($collection->id);

      // ✅ امسح كل الـ Cache المتعلق بهذه الـ Collection
      Cache::forget(CacheKeys::collection($collection->project_id, $collectionSlug));
      Cache::forget(CacheKeys::collectionById($collection->id));
      Cache::forget(CacheKeys::collectionItems($collection->id));
      Cache::forget(CacheKeys::collectionEntries($collection->id));
      Cache::forget(CacheKeys::collections($collection->project_id));
    });
  }
}
