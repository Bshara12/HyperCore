<?php

namespace App\Domains\CMS\Read\Repositories;

use Illuminate\Support\Facades\DB;

// class EntryTypeReadRepository
// {
//     public function getDataTypeId(int $entryId): ?int
//     {
//         return DB::table('data_entries')
//             ->where('id', $entryId)
//             ->value('data_type_id');
//     }

//     public function getPublishedEntriesByType(
//         int $dataTypeId
//     ) {

//         return DB::table('data_entries')
//             ->where('data_type_id', $dataTypeId)
//             // ->where('status', 'published')
//             ->where(function ($q) {
//                 $q->whereNull('scheduled_at')
//                   ->orWhere('scheduled_at', '<=', now());
//             })
//             ->pluck('id')
//             ->toArray();
//     }
// }

class EntryTypeReadRepository
{
  public function getDataTypeId(int $entryId): ?int
  {
    return DB::table('data_entries')
      ->where('id', $entryId)
      ->value('data_type_id');
  }

  public function queryPublishedByType(int $dataTypeId)
  {
    return DB::table('data_entries')
      ->where('data_type_id', $dataTypeId)
      ->where(function ($q) {
        $q->whereNull('scheduled_at')
          ->orWhere('scheduled_at', '<=', now());
      })
      ->whereNull('deleted_at');
  }
  public function filterPublishedByType(
    int $dataTypeId,
    ?string $dateFrom,
    ?string $dateTo,
    ?int $fieldId,
    ?string $searchValue
) {
    $query = DB::table('data_entries')
        ->where('data_type_id', $dataTypeId)
        ->where(function ($q) {
            $q->whereNull('scheduled_at')
              ->orWhere('scheduled_at', '<=', now());
        })
        ->whereNull('deleted_at');

    // 🔹 فلترة حسب تاريخ النشر
    if ($dateFrom) {
        $query->whereDate('published_at', '>=', $dateFrom);
    }

    if ($dateTo) {
        $query->whereDate('published_at', '<=', $dateTo);
    }

    // 🔹 بحث داخل data_entry_values
    // ملاحظة: البحث هنا مبني على value فقط (بغض النظر عن اللغة). دعم language=null ما بيحتاج OR،
    // لأنه طالما ما في فلترة لغة، صفوف language=null بتدخل طبيعي.
    if ($searchValue !== null && $searchValue !== '') {
        $query->whereExists(function ($subQuery) use ($fieldId, $searchValue) {
            $subQuery->select(DB::raw(1))
                ->from('data_entry_values as v')
                ->whereRaw('v.data_entry_id = data_entries.id')
                ->where('v.value', 'LIKE', "%{$searchValue}%");

            if ($fieldId) {
                $subQuery->where('v.data_type_field_id', $fieldId);
            }
        });
    }

    return $query;
}
}
