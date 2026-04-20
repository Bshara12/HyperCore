<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Actions\DataType\CreateDataTypeAction;
use App\Domains\CMS\Actions\DataType\UpdateDataTypeAction;
use App\Domains\CMS\Actions\DataType\DeleteDataTypeAction;
use App\Domains\CMS\Actions\DataType\ForceDeleteAction;
use App\Domains\CMS\Actions\DataType\RestoreDataTypeAction;
use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Models\DataType;

class DataTypeService
{
  public function __construct(
    protected CreateDataTypeAction $createAction,
    protected UpdateDataTypeAction $updateAction,
    protected DeleteDataTypeAction $deleteAction,
    protected RestoreDataTypeAction $restoreAction,
    protected ForceDeleteAction $forceDeleteAction,
    protected DataTypeRepositoryInterface $repository,
  ) {}

  public function create(CreateDataTypeDTO $dto)
  {
    return $this->createAction->execute($dto);
  }

  public function update(DataType $dataType, UpdateDataTypeDTO $dto)
  {
    return $this->updateAction->execute($dataType, $dto);
  }

  public function delete(DataType $dataType)
  {
    return $this->deleteAction->execute($dataType);
  }

  public function restore(int $dataTypeId)
  {
    return $this->restoreAction->execute($dataTypeId);
  }

  public function forceDelete(int $dataTypeId)
  {
    return $this->forceDeleteAction->execute($dataTypeId);
  }
}
