<?php

namespace App\Domains\CMS\Actions\DataType;

use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use App\Models\DataType;
use Illuminate\Support\Facades\Cache;

class UpdateDataTypeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'dataType.update';
  }

  public function __construct(
    protected DataTypeRepositoryInterface $repository
  ) {}

  public function execute(DataType $dataType, UpdateDataTypeDTO $dto)
  {
    return $this->run(function () use ($dataType, $dto) {
      $this->repository->ensureSlugIsUniqueForUpdate(
        projectId: $dataType->project_id,
        slug: $dto->slug,
        ignoreId: $dataType->id
      );

      $updated = $this->repository->update($dataType, $dto);
      Cache::forget(CacheKeys::dataType($dataType->id));
      Cache::forget(CacheKeys::dataTypeBySlug($dataType->slug, $dataType->project_id));
      Cache::forget(CacheKeys::dataTypes($dataType->project_id));
      return $updated;
      event(new SystemLogEvent(
        module: 'cms',
        eventType: 'update_datatype',
        userId: null,
        entityType: 'datatype',
        entityId:$dataType->id??null
      ));
      return $this->repository->update($dataType, $dto);
    });
  }
}
