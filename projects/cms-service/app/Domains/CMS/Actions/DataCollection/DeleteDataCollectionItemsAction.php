<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;

class DeleteDataCollectionItemsAction extends Action
{

  protected function circuitServiceName(): string
  {
    return 'dataCollection.deleteItems';
  }

  public function __construct(protected DataCollectionRepositoryInterface $repository) {}

  public function execute($collectionId)
  {
    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'delete_collection',
      userId: null,
      entityType: 'collection',
      entityId: $collectionId
    ));
    return $this->run(function () use ($collectionId) {
      return $this->repository->deleteItems($collectionId);
    });
  }
}
