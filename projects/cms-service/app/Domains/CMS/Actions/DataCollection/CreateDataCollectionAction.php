<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\Cache;

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

      $collection = $this->repository->create($dto);
      Cache::forget(CacheKeys::collections($dto->project_id));
      return $collection;
    });
  }
}
