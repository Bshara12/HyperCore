<?php

namespace App\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Actions\Action;

class ShowDataCollectionDetailsByIdAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.showDetailsById';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository,
  ) {}

  public function execute(int $collectionId)
  {
    return $this->run(function () use ($collectionId) {
      $collection = $this->repository->findById($collectionId);
      $collection['items'] = $this->repository->getCollectionItems($collection->id);
      return $collection;
    });
  }
}
