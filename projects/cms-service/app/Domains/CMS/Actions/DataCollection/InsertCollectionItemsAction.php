<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
<<<<<<< HEAD
use Illuminate\Support\Facades\Cache;
=======
use App\Events\SystemLogEvent;
>>>>>>> 3281b57fe309f120693e70fedad5e2094b119700

class InsertCollectionItemsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.insertItems';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository
  ) {}

  public function execute($dto)
  {
    return $this->run(function () use ($dto) {

      $collection = $this->repository->getBySlug($dto->collectionSlug);
      $this->repository->insertItems($collection->id, $dto->items);

      Cache::forget(CacheKeys::collectionItems($collection->id));
      Cache::forget(CacheKeys::collectionEntries($collection->id));
      Cache::forget(CacheKeys::collectionById($collection->id));
    });
    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'add_collection_item',
      userId: null,
      entityType: 'collection',
      entityId: $dto->slug
    ));
  }
}
