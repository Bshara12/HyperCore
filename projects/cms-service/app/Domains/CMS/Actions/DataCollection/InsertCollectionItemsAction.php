<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Actions\Action;

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
    $this->run(function () use ($dto) {
      $collection = $this->repository->getBySlug($dto->collectionSlug);
      return $this->repository->insertItems($collection->id, $dto->items);
    });
  }
}
