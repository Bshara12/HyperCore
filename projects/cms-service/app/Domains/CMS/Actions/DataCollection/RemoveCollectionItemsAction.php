<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;

class RemoveCollectionItemsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.removeItems';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository
  ) {}

  public function execute($dto)
  {
    $this->run(function () use ($dto) {
      $collection = $this->repository->getBySlug($dto->collectionSlug);
      return $this->repository->removeItems($collection->id, $dto->items);
    });
    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'delete_collection_item',
      userId: null,
      entityType: 'collection',
      entityId: $dto->collectionSlug
    ));
  }
}
