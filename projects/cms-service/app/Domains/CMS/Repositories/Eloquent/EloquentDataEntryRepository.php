<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Models\DataEntry;
use Illuminate\Support\Facades\DB;

class EloquentDataEntryRepository implements DataEntryRepositoryInterface
{
  public function create(array $data)
  {
    return DataEntry::create($data);
  }
  public function find(int $id): ?DataEntry
  {
    return DataEntry::find($id);
  }

  public function findOrFail(int $id): DataEntry
  {
    return DataEntry::findOrFail($id);
  }
  // public function findForProjectOrFail(
  //   int $entryId,
  //   int $projectId
  // ): object {
  //   return DB::table('data_entries')
  //     ->where('id', $entryId)
  //     ->where('project_id', $projectId)
  //     ->firstOrFail();
  // }
  public function findForProjectOrFail(int $entryId, int $projectId): DataEntry
  {
    return DataEntry::where('id', $entryId)
      ->where('project_id', $projectId)
      ->firstOrFail();
  }

  public function updateStatus(int $id, string $status): void
  {
    DB::table('data_entries')
      ->where('id', $id)
      ->update([
        'status' => $status,
        'updated_at' => now(),
      ]);
  }

  public function schedule(int $id, string $publishAt): void
  {
    DB::table('data_entries')->where('id', $id)->update([
      'status' => 'scheduled',
      'publish_at' => $publishAt,
    ]);
  }

  public function touchUpdatedBy(int $id, ?int $userId): void
  {
    DB::table('data_entries')->where('id', $id)->update([
      'updated_by' => $userId,
      'updated_at' => now(),
    ]);
  }

  public function pluckIdsByProjectTypeAndValues(
    int $projectId,
    int $dataTypeId,
    array $values
  ): array {
    if (empty($values)) {
      return [];
    }

    return DataEntry::query()
      ->where('project_id', $projectId)
      ->where('data_type_id', $dataTypeId)
      ->whereHas('values', function ($v) use ($values) {
        $v->whereIn('value', $values);
      })
      ->pluck('id')
      ->toArray();
  }

  public function pluckIdsForProjectTypeExcluding(
    int $projectId,
    int $dataTypeId,
    array $excludedEntryIds
  ): array {
    $query = DataEntry::query()
      ->where('project_id', $projectId)
      ->where('data_type_id', $dataTypeId);

    if (!empty($excludedEntryIds)) {
      $query->whereNotIn('id', $excludedEntryIds);
    }

    return $query->pluck('id')->toArray();
  }

  public function pluckIdsForProject(int $projectId): array
  {
    return DataEntry::query()
      ->where('project_id', $projectId)
      ->pluck('id')
      ->toArray();
  }


  public function updateRatingStats(int $id, array $data): void
  {
    DataEntry::where('id', $id)->update([
      'ratings_count' => $data['ratings_count'],
      'ratings_avg' => $data['ratings_avg'],
    ]);
  }


  public function getRatingStats(int $id): array
  {
    $data = DataEntry::select('ratings_count', 'ratings_avg')
      ->where('id', $id)
      ->first();

    if (!$data) {
      return [
        'ratings_count' => 0,
        'ratings_avg' => 0
      ];
    }

    return [
      'ratings_count' => $data->ratings_count,
      'ratings_avg' => (float) $data->ratings_avg,
    ];
  }
}
