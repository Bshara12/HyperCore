<?php

namespace App\Domains\CMS\Actions\DataType;

use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use App\Models\DataType;
use Illuminate\Support\Facades\Cache;

class DeleteDataTypeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.delete';
  }

  public function __construct(
    protected DataTypeRepositoryInterface $repository
  ) {}

  public function execute(DataType $dataType): void
  {
    $this->run(function () use ($dataType) {
      $this->repository->delete($dataType);
<<<<<<< HEAD
      Cache::forget(CacheKeys::dataType($dataType->id));
      Cache::forget(CacheKeys::dataTypeBySlug($dataType->slug, $dataType->project_id));
      Cache::forget(CacheKeys::dataTypes($dataType->project_id));
=======
        event(new SystemLogEvent(
        module: 'cms',
        eventType: 'delete_datatype',
        userId: null,
        entityType: 'datatype',
        entityId: $dataType->id
      ));
>>>>>>> 3281b57fe309f120693e70fedad5e2094b119700
    });
  }
}
