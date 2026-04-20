<?php

namespace App\Domains\CMS\Read\Repositories;

use Illuminate\Support\Facades\DB;

class EntryRelationRepository
{
  public function getParentIds(int $entryId): array
  {
    return DB::table('data_entry_relations')
      ->where('data_entry_id', $entryId)
      ->pluck('related_entry_id')
      ->toArray();
  }

  public function getChildIds(int $entryId): array
  {
    return DB::table('data_entry_relations')
      ->where('related_entry_id', $entryId)
      ->pluck('data_entry_id')
      ->toArray();
  }
  public function getAllByProject(int $projectId): array
  {
    return DB::table('data_entry_relations as r')
      ->join('data_entries as e', 'e.id', '=', 'r.data_entry_id')
      ->where('e.project_id', $projectId)
      ->select([
        'r.data_entry_id as parent_id',
        'r.related_entry_id as child_id'
      ])
      ->get()
      ->map(fn($r) => [
        'parent_id' => $r->parent_id,
        'child_id' => $r->child_id,
      ])
      ->toArray();
  }
}
