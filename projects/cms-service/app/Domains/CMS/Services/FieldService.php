<?php

namespace App\Domains\CMS\Services;


use App\Domains\CMS\Actions\Field\CreateFieldAction;
use App\Domains\CMS\Actions\Field\DeleteFieldAction;
use App\Domains\CMS\Actions\Field\ForceDeleteAction;
use App\Domains\CMS\Actions\Field\RestoreFieldAction;
use App\Domains\CMS\Actions\Field\UpdateFieldAction;
use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Models\DataTypeField;

class FieldService
{
  public function __construct(
    protected CreateFieldAction $createAction,
    protected UpdateFieldAction $updateAction,
    protected DeleteFieldAction $deleteAction,
    protected RestoreFieldAction $restoreAction,
    protected ForceDeleteAction $forceDeleteAction,
  ) {}

  public function create(CreateFieldDTO $dto)
  {
    return $this->createAction->execute($dto);
  }

  public function update(DataTypeField $field, CreateFieldDTO $dto)
  {
    return $this->updateAction->execute($field, $dto);
  }

  public function destroy(DataTypeField $field)
  {
    $this->deleteAction->execute($field);
  }

  public function restore(int $fieldId)
  {
    $this->restoreAction->execute($fieldId);
  }

  public function forceDelete(int $fieldId)
  {
    $this->forceDeleteAction->execute($fieldId);
  }
}
