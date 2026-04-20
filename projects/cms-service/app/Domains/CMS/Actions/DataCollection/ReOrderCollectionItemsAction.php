<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Actions\Action;

class ReOrderCollectionItemsAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.reOrderItems';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository
  ) {}

  public function execute($dto)
  {
    return $this->run(function () use ($dto) {
      $collection = $this->repository->getBySlug($dto->collectionSlug);
      return $this->repository->reOrderItems($collection->id, $dto->items);
    });
  }
}
