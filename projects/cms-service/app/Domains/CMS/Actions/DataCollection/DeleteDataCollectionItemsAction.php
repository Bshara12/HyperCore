<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

class DeleteDataCollectionItemsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.deleteItems';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository
  ) {}

  public function execute($collectionId)
  {
    return $this->run(function () use ($collectionId) {

      $result = $this->repository->deleteItems($collectionId);

      Cache::forget(CacheKeys::collectionItems($collectionId));
      Cache::forget(CacheKeys::collectionEntries($collectionId));
      Cache::forget(CacheKeys::collectionById($collectionId));

      return $result;
    });
  }
}
