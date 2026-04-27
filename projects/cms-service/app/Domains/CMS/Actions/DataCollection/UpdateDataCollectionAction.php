<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;
use App\Events\SystemLogEvent;

class UpdateDataCollectionAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.update';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository
  ) {}

  public function execute($dto)
  {
    return $this->run(function () use ($dto) {

      $collection = $this->repository->update($dto);

      Cache::forget(CacheKeys::collectionById($dto->collection_id));
      Cache::forget(CacheKeys::collectionItems($dto->collection_id));
      Cache::forget(CacheKeys::collectionEntries($dto->collection_id));
      Cache::forget(CacheKeys::collections($collection->project_id));


      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'update_collection',
        userId: null,
        entityType: 'collection',
        entityId: $dto->collection_id
      ));
      return $collection;

      // return $this->repository->update($dto);
    });
  }
}
