<?php

namespace App\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
<<<<<<< HEAD
use Illuminate\Support\Facades\Cache;
=======
use App\Events\SystemLogEvent;
>>>>>>> 3281b57fe309f120693e70fedad5e2094b119700

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
<<<<<<< HEAD

      $collection = $this->repository->create($dto);
      Cache::forget(CacheKeys::collections($dto->project_id));
      return $collection;
=======
      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'collection_create',
        userId:null,
        entityType: 'collection',
        entityId: null
      ));
      return $this->repository->create($dto);
>>>>>>> 3281b57fe309f120693e70fedad5e2094b119700
    });
  }
}
