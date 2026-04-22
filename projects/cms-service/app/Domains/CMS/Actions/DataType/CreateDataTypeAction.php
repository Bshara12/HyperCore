<?php

namespace App\Domains\CMS\Actions\DataType;

use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
<<<<<<< HEAD
use Illuminate\Support\Facades\Cache;
=======
use App\Events\SystemLogEvent;
>>>>>>> 3281b57fe309f120693e70fedad5e2094b119700

class CreateDataTypeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.create';
  }

  public function __construct(
    protected DataTypeRepositoryInterface $repository
  ) {}

  public function execute(CreateDataTypeDTO $dto)
  {
    return $this->run(function () use ($dto) {
      $this->repository->ensureSlugIsUnique(
        projectId: $dto->project_id,
        slug: $dto->slug
      );

      $dataType = $this->repository->create($dto);
<<<<<<< HEAD
      Cache::forget(CacheKeys::dataTypes($dto->project_id));
=======


      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'create_datatype',
        userId: null,
        entityType: 'datatype',
        entityId: null
      ));

>>>>>>> 3281b57fe309f120693e70fedad5e2094b119700
      return $dataType;
    });
  }
}
