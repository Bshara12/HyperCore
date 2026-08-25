<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CollectionCache;
use App\Domains\Core\Actions\Action;
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

      // $dto->slug is the slug the collection was addressed by; $collection->slug
      // is what it holds now. They are equal while the slug stays immutable —
      // forgetting both keeps this correct if that ever changes, instead of
      // leaving the old key serving stale data for a full TTL.
      CollectionCache::forgetAll($dto->project_id, $dto->slug, $dto->collection_id);
      CollectionCache::forgetCollection($collection->project_id, $collection->slug);

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
