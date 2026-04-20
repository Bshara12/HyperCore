<?php

namespace App\Domains\CMS\Read\Repositories\DataType;

use App\Models\DataType;

class DataTypeRepositoryRead
{
  public function list(int $projectId)
  {
    return DataType::where('project_id', $projectId)
      ->orderBy('name')
      ->get();
  }

  public function trashed(int $projectId)
  {
    return DataType::onlyTrashed()->where('project_id', $projectId)->get();
  }

  public function findBySlug(string $slug, int $projectId): ?DataType
  {
    return DataType::where('project_id', $projectId)
      ->where('slug', $slug)
      ->first();
  }
}
