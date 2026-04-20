<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\Repositories\Interface\DataEntryRelationRepository;
use App\Models\DataEntryRelation;
use Illuminate\Support\Facades\DB;

class EloquentDataEntryRelationRepository implements DataEntryRelationRepository
{
  public function insertForEntry(
    int $entryId,
    int $dataTypeId,
    int $projectId,
    array $relations
  ): void {

    $rows = [];
    $now = now();

    foreach ($relations as $relation) {

      $relationId = $relation['relation_id'];

      // ✅ 1️⃣ تحقق أن relation تنتمي لنفس data type
      $relationExists = DB::table('data_type_relations')
        ->where('id', $relationId)
        ->where('data_type_id', $dataTypeId)
        ->exists();

      if (!$relationExists) {
        throw new \Exception("Invalid relation: relation does not belong to this data type.");
      }

      foreach ($relation['related_entry_ids'] as $relatedId) {

        // ✅ 2️⃣ تحقق أن related entry موجود وينتمي لنفس المشروع
        $relatedEntry = DB::table('data_entries')
          ->where('id', $relatedId)
          ->where('project_id', $projectId)
          ->first();

        if (!$relatedEntry) {
          throw new \Exception("Related entry {$relatedId} not found in this project.");
        }

        $rows[] = [
          'data_entry_id' => $entryId,
          'related_entry_id' => $relatedId,
          'data_type_relation_id' => $relationId,
          'created_at' => $now,
          'updated_at' => $now,
        ];
      }
    }

    if (!empty($rows)) {
      DB::table('data_entry_relations')->insert($rows);
    }
  }

  public function deleteForEntry(int $entryId): void
  {
    DB::table('data_entry_relations')
      ->where('data_entry_id', $entryId)
      ->delete();
  }

  public function deleteWhereRelatedIs(int $relatedId): void
  {
    DataEntryRelation::where('related_entry_id', $relatedId)->delete();
  }

  public function getEntriesWhereRelatedIs(int $relatedId): array
  {
    return DataEntryRelation::where('related_entry_id', $relatedId)->get()->toArray();
  }

  public function pluckEntryIdsWhereRelatedIs(int $relatedId): array
  {
    return DataEntryRelation::query()
      ->where('related_entry_id', $relatedId)
      ->whereHas('dataTypeRelation', function ($q) {
        $q->where('relation_type', 'many_to_one');
      })
      ->pluck('data_entry_id')
      ->toArray();
  }

  public function pluckEntryIdsByRelatedIds(array $relatedIds): array
  {
    if (empty($relatedIds)) {
      return [];
    }

    return DataEntryRelation::query()
      ->whereIn('related_entry_id', $relatedIds)
      ->pluck('data_entry_id')
      ->toArray();
  }

  public function pluckEntryIdsByRelatedIdsWithin(array $relatedIds, array $withinEntryIds): array
  {
    if (empty($relatedIds) || empty($withinEntryIds)) {
      return [];
    }

    return DataEntryRelation::query()
      ->whereIn('related_entry_id', $relatedIds)
      ->whereIn('data_entry_id', $withinEntryIds)
      ->pluck('data_entry_id')
      ->toArray();
  }
}
