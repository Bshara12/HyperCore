<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Actions\Action;

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
    $this->run(function () use ($collectionSlug) {
      $collection = $this->repository->getBySlug($collectionSlug);
      return $this->repository->delete($collection->id);
    });
  }
}
