<?php

namespace App\Domains\CMS\Read\Repositories;

use Illuminate\Support\Facades\DB;

class DataTypeRepository implements DataTypeRepositoryInterface
{
  public function getIdBySlugAndProject(string $slug, int $projectId): ?int
  {
    return DB::table('data_types')
      ->where('slug', $slug)
      ->where('project_id', $projectId)
      ->value('id');
  }
}
