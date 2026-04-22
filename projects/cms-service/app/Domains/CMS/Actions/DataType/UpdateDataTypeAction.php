<?php

namespace App\Domains\CMS\Actions\DataType;

use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\Core\Actions\Action;
use App\Events\SystemLogEvent;
use App\Models\DataType;

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
