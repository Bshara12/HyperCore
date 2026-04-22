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

class DeactivateCollectionAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataCollection.deactivate';
  }

  public function __construct(
    protected DataCollectionRepositoryInterface $repository
  ) {}

  public function execute($dto)
  {
    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'deactivate_collection',
      userId: null,
      entityType: 'collection',
      entityId: $dto->slug??null
    ));
    return $this->run(function () use ($dto) {

      $result = $this->repository->deactivate($dto);

      Cache::forget(CacheKeys::collection($dto->project_id, $dto->slug));
      Cache::forget(CacheKeys::collections($dto->project_id));

      return $result;
    });
  }
}
