<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Actions\Action;

class CreateDataCollectionAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.create';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository
  ) {}

  public function execute($dto)
  {
    return $this->run(function () use ($dto) {
      return $this->repository->create($dto);
    });
  }
}
