<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CollectionCache;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use App\Models\DataCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
    // Resolved before run(): a missing collection is a 404, not a transient
    // failure, and anything thrown inside run() other than a validation error
    // gets retried three times and rethrown as a generic 500.
    $collection = $this->repository->getBySlug($collectionSlug);

    if (! $collection) {
      throw (new ModelNotFoundException)->setModel(DataCollection::class);
    }

    $projectId = $collection->project_id;
    $collectionId = $collection->id;

    $this->run(function () use ($collectionId) {
      $this->repository->delete($collectionId);
    });

    CollectionCache::forgetAll($projectId, $collectionSlug, $collectionId);

    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'delete_collection',
      userId: null,
      entityType: 'collection',
      entityId: $collectionId
    ));
  }
}
