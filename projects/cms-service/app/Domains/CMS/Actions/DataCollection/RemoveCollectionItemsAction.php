<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

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
    return $this->run(function () use ($dto) {

      $collection = $this->repository->getBySlug($dto->collectionSlug);
      $this->repository->removeItems($collection->id, $dto->items);

      Cache::forget(CacheKeys::collectionItems($collection->id));
      Cache::forget(CacheKeys::collectionEntries($collection->id));
      Cache::forget(CacheKeys::collectionById($collection->id));
    });
  }
}
