<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Models\DataType;
use Illuminate\Support\Facades\DB;

class DataTypeRepositoryEloquent implements DataTypeRepositoryInterface
{
  public function create(CreateDataTypeDTO $dto): DataType
  {
    return DataType::create([
      'project_id'   => $dto->project_id,
      'name'         => $dto->name,
      'slug'         => $dto->slug,
      'description'  => $dto->description,
      'is_active'    => $dto->is_active ?? true,
      'settings'     => $dto->settings ?? []
    ]);
  }

  public function ensureSlugIsUnique(int $projectId, string $slug): void
  {
    $exists = DataType::where('project_id', $projectId)
      ->where('slug', $slug)
      ->exists();

    if ($exists) {
      abort(422, "Slug '{$slug}' already exists for this project.");
    }
  }

  public function findBySlug(string $slug, int $projectId): ?DataType
  {
    return DataType::where('project_id', $projectId)
      ->where('slug', $slug)
      ->first();
  }

  public function ensureSlugIsUniqueForUpdate(int $projectId, string $slug, int $ignoreId): void
  {
    $exists = DataType::where('project_id', $projectId)
      ->where('slug', $slug)
      ->where('id', '!=', $ignoreId)
      ->exists();

    if ($exists) {
      abort(422, "Slug '{$slug}' already exists for this project.");
    }
  }

  public function update(DataType $dataType, UpdateDataTypeDTO $dto): DataType
  {
    $dataType->update([
      'name'        => $dto->name,
      'slug'        => $dto->slug,
      'description' => $dto->description,
      'is_active'   => $dto->is_active,
      'settings'    => $dto->settings,
    ]);

    return $dataType;
  }

  public function delete(DataType $dataType): void
  {
    $dataType->delete();
  }

  public function restore(int $dataTypeId): void
  {
    $dataType = DataType::onlyTrashed()->findOrFail($dataTypeId);
    $dataType->restore();
  }

  public function forceDelete(int $dataTypeId): void
  {
    $dataType = DataType::findOrFail($dataTypeId);
    $dataType->forceDelete();
  }

  // test

  public function getIdBySlugAndProject(string $slug, int $projectId): ?int
  {
    return DB::table('data_types')
      ->where('slug', $slug)
      ->where('project_id', $projectId)
      ->value('id');
  }
}
