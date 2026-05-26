<?php

namespace App\Domains\CMS\Actions\DataType;

use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;

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
        projectId: (int) $dto->project_id,
        slug: $dto->slug
      );

      $dataType = $this->repository->create($dto);
      Cache::forget(CacheKeys::dataTypes( (int)$dto->project_id));

      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'create_datatype',
        userId: null,
        entityType: 'datatype',
        entityId: null
      ));

      return $dataType;
    });
  }
}
