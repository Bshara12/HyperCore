<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\DTOs\Field\CreateFieldDTO;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Models\DataTypeField;
use Illuminate\Support\Facades\DB;

class FieldRepositoryEloquent implements FieldRepositoryInterface
{
  public function ensureFieldIsUnique(int $data_type_id, string $name): void
  {
    $exists = DataTypeField::where('data_type_id', $data_type_id)
      ->where('name', $name)
      ->exists();

    if ($exists) {
      abort(422, "Field '{$name}' already exists for this Data-Type.");
    }
  }

  public function ensureUpdatedFieldIsUnique(int $data_type_id, string $name, int $field_id): void
  {
    $exists = DataTypeField::where('data_type_id', $data_type_id)
      ->where('name', $name)
      ->where('id', '!=', $field_id)
      ->exists();

    if ($exists) {
      abort(422, "Field '{$name}' already exists for this Data-Type.");
    }
  }

  public function findByDataTypeAndName(int $dataTypeId, string $name): ?DataTypeField
  {
    return DataTypeField::where('data_type_id', $dataTypeId)
      ->where('name', $name)
      ->first();
  }

  public function create(CreateFieldDTO $dto, array $normalizedSettings): DataTypeField
  {
    return DataTypeField::create([
      'data_type_id'     => $dto->data_type_id,
      'name'             => $dto->name,
      'type'             => $dto->type,
      'required'         => $dto->required,
      'translatable'     => $dto->translatable,
      'validation_rules' => $dto->validation_rules,
      'settings'         => $normalizedSettings,
      'sort_order'       => $dto->sort_order,
    ]);
  }

  public function update(CreateFieldDTO $dto, DataTypeField $field, array $normalizedSettings): DataTypeField
  {
    $field->update([
      'name'             => $dto->name,
      'required'         => $dto->required,
      'translatable' => $dto->translatable,
      'validation_rules' => $dto->validation_rules,
      'settings' => $normalizedSettings,
      'sort_order' => $dto->sort_order,
    ]);

    return $field->fresh();
  }

  public function getByDataType(int $dataTypeId)
  {
    return DB::table('data_type_fields')
      ->where('data_type_id', $dataTypeId)
      ->get()
      ->keyBy('name');
  }

  public function delete(DataTypeField $field)
  {
    $field->delete();
  }

  public function restore(int $fieldId): void
  {
    $field = DataTypeField::onlyTrashed()->findOrFail($fieldId);
    $field->restore();
  }

  public function forceDelete(int $fieldId): void
  {
    $field = DataTypeField::findOrFail($fieldId);
    $field->forceDelete();
  }
}
