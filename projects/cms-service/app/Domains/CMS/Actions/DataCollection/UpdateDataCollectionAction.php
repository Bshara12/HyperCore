<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Actions\Action;

class UpdateDataCollectionAction extends Action
{

  protected function circuitServiceName(): string
  {
    return 'dataCollection.update';
  }

  public function __construct(protected DataCollectionRepositoryInterface $repository) {}

  public function execute($dto)
  {
    return $this->run(function () use ($dto) {
      return $this->repository->update($dto);
    });
  }
}
