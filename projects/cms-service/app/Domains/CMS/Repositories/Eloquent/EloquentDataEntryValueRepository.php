<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Models\DataCollection;
use App\Models\DataCollectionItem;
use Illuminate\Support\Facades\DB;

// class EloquentDataEntryValueRepository implements DataEntryValueRepository
// {

//   public function bulkInsert(
//     int $entryId,
//     int $dataTypeId,
//     array $values
//   ): void {
//     // 1️⃣ جلب الحقول
//     $fields = DB::table('data_type_fields')
//       ->where('data_type_id', $dataTypeId)
//       ->get()
//       ->keyBy('name'); // name = slug تبع الحقل

//     $rows = [];
//     $now = now();

//     foreach ($values as $fieldSlug => $langs) {
//       if (!isset($fields[$fieldSlug])) {
//         throw new \Exception("Field {$fieldSlug} does not exist in this data type.");
//       }


//       $fieldId = $fields[$fieldSlug]->id;

//       // foreach ($langs as $lang => $value) {
//       //   $rows[] = [
//       //     'data_entry_id' => $entryId,
//       //     'data_type_field_id' => $fieldId,
//       //     'language' => $lang,
//       //     'value' => (string) $value,
//       //     'created_at' => $now,
//       //     'updated_at' => $now,
//       //   ];
//       // }

//       // foreach ($langs as $lang => $value) {
//       $langs = $this->normalizeFieldValue($langs);

//       foreach ($langs as $lang => $value) {

//         // ✅ إذا القيمة array (مثل file field)
//         if (is_array($value)) {

//           foreach ($value as $singleValue) {
//             $normalizedLang = ($lang === 'default' || $lang === null || $lang === '') ? null : $lang;
//             $rows[] = [
//               'data_entry_id' => $entryId,
//               'data_type_field_id' => $fieldId,
//               'language' => $normalizedLang,
//               'value' => (string) $singleValue,
//               'created_at' => $now,
//               'updated_at' => $now,
//             ];
//           }
//         } else {

//           // ✅ الحقول العادية (text, number...)
//           $rows[] = [
//             'data_entry_id' => $entryId,
//             'data_type_field_id' => $fieldId,
//             'language' => $normalizedLang,
//             'value' => (string) $value,
//             'created_at' => $now,
//             'updated_at' => $now,
//           ];
//         }
//       }
//     }

//     DB::table('data_entry_values')->insert($rows);
//   }

//   public function replacePartial(
//     int $entryId,
//     int $dataTypeId,
//     array $values
//   ): void {
//     // 1️⃣ جلب الحقول
//     $fields = DB::table('data_type_fields')
//       ->where('data_type_id', $dataTypeId)
//       ->get()
//       ->keyBy('name'); // name = slug تبع الحقل

//     $rows = [];
//     $now = now();

//     foreach ($values as $fieldSlug => $langs) {
//       if (!isset($fields[$fieldSlug])) {
//         throw new \Exception("Field {$fieldSlug} does not exist in this data type.");
//       }

//       $fieldId = $fields[$fieldSlug]->id;

//       // foreach ($langs as $lang => $value) {
//       $langs = $this->normalizeFieldValue($langs);

//       foreach ($langs as $lang => $value) {
//         DB::table('data_entry_values')
//           ->where('data_entry_id', $entryId)
//           ->where('data_type_field_id', $fieldId)
//           ->where('language', $lang)
//           ->delete();
//         $normalizedLang = ($lang === 'default' || $lang === null || $lang === '') ? null : $lang;
//         if (is_array($value)) {
//           foreach ($value as $singleValue) {
//             $rows[] = [
//               'data_entry_id' => $entryId,
//               'data_type_field_id' => $fieldId,
//               'language' => $normalizedLang,
//               'value' => (string) $singleValue,
//               'created_at' => $now,
//               'updated_at' => $now,
//             ];
//           }
//         } else {
//           $rows[] = [
//             'data_entry_id' => $entryId,
//             'data_type_field_id' => $fieldId,
//             'language' => $normalizedLang,
//             'value' => (string) $value,
//             'created_at' => $now,
//             'updated_at' => $now,
//           ];
//         }
//       }
//     }

//     if (!empty($rows)) {
//       DB::table('data_entry_values')->insert($rows);
//     }
//   }


//   private function normalizeFieldValue($value): array
//   {
//     // case 1: translatable
//     if (is_array($value)) {
//       return $value;
//     }

//     // case 2: non-translatable
//     return [
//       null => $value
//     ];
//   }


//   public function getForEntry(int $entryId): array
//   {
//     return DB::table('data_entry_values')
//       ->where('data_entry_id', $entryId)
//       ->get()
//       ->map(fn($row) => (array) $row)
//       ->toArray();
//   }
//   public function deleteForEntry(int $entryId): void
//   {
//     DB::table('data_entry_values')
//       ->where('data_entry_id', $entryId)
//       ->delete();
//   }

//   public function bulkInsertFromSnapshot(int $entryId, array $values): void
//   {
//     $rows = [];
//     $now = now();

//     foreach ($values as $row) {
//       $rows[] = [
//         'data_entry_id' => $entryId,
//         'data_type_field_id' => $row['data_type_field_id'],
//         'language' => $row['language'],
//         'value' => $row['value'],
//         'created_at' => $now,
//         'updated_at' => $now,
//       ];
//     }

//     DB::table('data_entry_values')->insert($rows);
//   }

//   public function pluckEntryIdsByFieldComparison(string $field, string $operator, $value): array
//   {
//     return DB::table('data_entry_values')
//       ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
//       ->where('data_type_fields.name', $field)
//       ->where('data_entry_values.value', $operator, $value)
//       ->pluck('data_entry_values.data_entry_id')
//       ->toArray();
//   }

//   public function pluckEntryIdsByFieldLike(string $field, string $pattern): array
//   {
//     return DB::table('data_entry_values')
//       ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
//       ->where('data_type_fields.name', $field)
//       ->where('data_entry_values.value', 'LIKE', $pattern)
//       ->pluck('data_entry_values.data_entry_id')
//       ->toArray();
//   }

//   public function pluckEntryIdsByFieldIn(string $field, array $values): array
//   {
//     if (empty($values)) {
//       return [];
//     }

//     return DB::table('data_entry_values')
//       ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
//       ->where('data_type_fields.name', $field)
//       ->whereIn('data_entry_values.value', $values)
//       ->pluck('data_entry_values.data_entry_id')
//       ->toArray();
//   }

//   public function pluckEntryIdsByFieldInCollection(int $projectId, int $dataTypeId, array $values): array
//   {
//     return DataCollection::where('project_id', $projectId)
//       ->where('data_type_id', $dataTypeId)
//       ->whereIn('slug', $values)
//       ->pluck('id')
//       ->toArray();
//   }

//   public function returnEntryIdsFromCollectionItems(array $collectionIds): array
//   {
//     return DataCollectionItem::whereIn('collection_id', $collectionIds)
//       ->pluck('item_id')
//       ->unique()
//       ->toArray();
//   }

//   public function pluckEntryIdsByFieldBetween(string $field, array $values): array
//   {
//     if (count($values) !== 2) {
//       return [];
//     }

//     return DB::table('data_entry_values')
//       ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
//       ->where('data_type_fields.name', $field)
//       ->whereBetween('data_entry_values.value', [$values[0], $values[1]])
//       ->pluck('data_entry_values.data_entry_id')
//       ->toArray();
//   }

//   public function pluckEntryIdsByFieldComparisonWithin(string $field, string $operator, $value, array $withinEntryIds): array
//   {
//     if (empty($withinEntryIds)) {
//       return [];
//     }

//     return DB::table('data_entry_values')
//       ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
//       ->where('data_type_fields.name', $field)
//       ->whereIn('data_entry_values.data_entry_id', $withinEntryIds)
//       ->where('data_entry_values.value', $operator, $value)
//       ->pluck('data_entry_values.data_entry_id')
//       ->toArray();
//   }

//   public function pluckEntryIdsByFieldLikeWithin(string $field, string $pattern, array $withinEntryIds): array
//   {
//     if (empty($withinEntryIds)) {
//       return [];
//     }

//     return DB::table('data_entry_values')
//       ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
//       ->where('data_type_fields.name', $field)
//       ->whereIn('data_entry_values.data_entry_id', $withinEntryIds)
//       ->where('data_entry_values.value', 'LIKE', $pattern)
//       ->pluck('data_entry_values.data_entry_id')
//       ->toArray();
//   }

//   public function pluckEntryIdsByFieldInWithin(string $field, array $values, array $withinEntryIds): array
//   {
//     if (empty($values) || empty($withinEntryIds)) {
//       return [];
//     }

//     return DB::table('data_entry_values')
//       ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
//       ->where('data_type_fields.name', $field)
//       ->whereIn('data_entry_values.data_entry_id', $withinEntryIds)
//       ->whereIn('data_entry_values.value', $values)
//       ->pluck('data_entry_values.data_entry_id')
//       ->toArray();
//   }

//   public function pluckEntryIdsByFieldBetweenWithin(string $field, array $values, array $withinEntryIds): array
//   {
//     if (count($values) !== 2 || empty($withinEntryIds)) {
//       return [];
//     }

//     return DB::table('data_entry_values')
//       ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
//       ->where('data_type_fields.name', $field)
//       ->whereIn('data_entry_values.data_entry_id', $withinEntryIds)
//       ->whereBetween('data_entry_values.value', [$values[0], $values[1]])
//       ->pluck('data_entry_values.data_entry_id')
//       ->toArray();
//   }

//   public function pluckNumericFieldValuesByEntryIds(string $field, array $entryIds): array
//   {
//     if (empty($entryIds)) {
//       return [];
//     }

//     $rows = DB::table('data_entry_values')
//       ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
//       ->where('data_type_fields.name', $field)
//       ->whereIn('data_entry_values.data_entry_id', $entryIds)
//       ->select(['data_entry_values.data_entry_id as entry_id', 'data_entry_values.value as value'])
//       ->get();

//     $out = [];
//     foreach ($rows as $row) {
//       $out[(int)$row->entry_id] = (float)$row->value;
//     }

//     return $out;
//   }
// }

class EloquentDataEntryValueRepository implements DataEntryValueRepository
{

  public function bulkInsert(
    int $entryId,
    int $dataTypeId,
    array $values
  ): void {
    $fields = DB::table('data_type_fields')
      ->where('data_type_id', $dataTypeId)
      ->get()
      ->keyBy('name');

    $rows = [];
    $now = now();

    foreach ($values as $fieldSlug => $langs) {
      if (!isset($fields[$fieldSlug])) {
        throw new \Exception("Field {$fieldSlug} does not exist in this data type.");
      }

      $fieldId = $fields[$fieldSlug]->id;

      $langs = $this->normalizeFieldValue($langs);

      foreach ($langs as $lang => $value) {

        $normalizedLang = $this->normalizeLang($lang);

        $valueList = is_array($value) ? $value : [$value];

        foreach ($valueList as $singleValue) {
          $rows[] = [
            'data_entry_id' => $entryId,
            'data_type_field_id' => $fieldId,
            'language' => $normalizedLang,
            'value' => (string) $singleValue,
            'created_at' => $now,
            'updated_at' => $now,
          ];
        }
      }
    }

    if (!empty($rows)) {
      DB::table('data_entry_values')->insert($rows);
    }
  }

  public function replacePartial(
    int $entryId,
    int $dataTypeId,
    array $values
  ): void {
    $fields = DB::table('data_type_fields')
      ->where('data_type_id', $dataTypeId)
      ->get()
      ->keyBy('name');

    $rows = [];
    $now = now();

    foreach ($values as $fieldSlug => $langs) {
      if (!isset($fields[$fieldSlug])) {
        throw new \Exception("Field {$fieldSlug} does not exist in this data type.");
      }

      $fieldId = $fields[$fieldSlug]->id;

      $langs = $this->normalizeFieldValue($langs);

      foreach ($langs as $lang => $value) {

        $normalizedLang = $this->normalizeLang($lang);

        // // ✅ FIX: delete correctly (NULL vs value)
        // $query = DB::table('data_entry_values')
        //   ->where('data_entry_id', $entryId)
        //   ->where('data_type_field_id', $fieldId);

        // if ($normalizedLang === null) {
        //   $query->whereNull('language');
        // } else {
        //   $query->where('language', $normalizedLang);
        // }

        // $query->delete();
        $isNonTranslatable = ($normalizedLang === null && count($langs) === 1);

        $query = DB::table('data_entry_values')
          ->where('data_entry_id', $entryId)
          ->where('data_type_field_id', $fieldId);

        if ($isNonTranslatable) {
          // 🔥 حذف كل اللغات (لأنو عم يتحول لحقل غير مترجم)
          $query->delete();
        } else {
          // ✅ حذف حسب اللغة فقط
          if ($normalizedLang === null) {
            $query->whereNull('language');
          } else {
            $query->where('language', $normalizedLang);
          }

          $query->delete();
        }


        

        $valueList = is_array($value) ? $value : [$value];

        foreach ($valueList as $singleValue) {
          $rows[] = [
            'data_entry_id' => $entryId,
            'data_type_field_id' => $fieldId,
            'language' => $normalizedLang,
            'value' => (string) $singleValue,
            'created_at' => $now,
            'updated_at' => $now,
          ];
        }
      }
    }

    if (!empty($rows)) {
      DB::table('data_entry_values')->insert($rows);
    }
  }

  /**
   * Normalize incoming field value
   */
  private function normalizeFieldValue($value): array
  {
    if (is_array($value)) {
      return $value;
    }

    return [
      null => $value
    ];
  }

  /**
   * Normalize language (🔥 أهم شي)
   */
  private function normalizeLang($lang): ?string
  {
    return ($lang === 'default' || $lang === '' || $lang === null)
      ? null
      : $lang;
  }

  public function getForEntry(int $entryId): array
  {
    return DB::table('data_entry_values')
      ->where('data_entry_id', $entryId)
      ->get()
      ->map(fn($row) => (array) $row)
      ->toArray();
  }

  public function deleteForEntry(int $entryId): void
  {
    DB::table('data_entry_values')
      ->where('data_entry_id', $entryId)
      ->delete();
  }

  public function bulkInsertFromSnapshot(int $entryId, array $values): void
  {
    $rows = [];
    $now = now();

    foreach ($values as $row) {
      $rows[] = [
        'data_entry_id' => $entryId,
        'data_type_field_id' => $row['data_type_field_id'],
        'language' => $row['language'],
        'value' => $row['value'],
        'created_at' => $now,
        'updated_at' => $now,
      ];
    }

    DB::table('data_entry_values')->insert($rows);
  }

  public function pluckEntryIdsByFieldComparison(string $field, string $operator, $value): array
  {
    return DB::table('data_entry_values')
      ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
      ->where('data_type_fields.name', $field)
      ->where('data_entry_values.value', $operator, $value)
      ->pluck('data_entry_values.data_entry_id')
      ->toArray();
  }

  public function pluckEntryIdsByFieldLike(string $field, string $pattern): array
  {
    return DB::table('data_entry_values')
      ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
      ->where('data_type_fields.name', $field)
      ->where('data_entry_values.value', 'LIKE', $pattern)
      ->pluck('data_entry_values.data_entry_id')
      ->toArray();
  }

  public function pluckEntryIdsByFieldIn(string $field, array $values): array
  {
    if (empty($values)) {
      return [];
    }

    return DB::table('data_entry_values')
      ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
      ->where('data_type_fields.name', $field)
      ->whereIn('data_entry_values.value', $values)
      ->pluck('data_entry_values.data_entry_id')
      ->toArray();
  }

  public function pluckEntryIdsByFieldInCollection(int $projectId, int $dataTypeId, array $values): array
  {
    return DataCollection::where('project_id', $projectId)
      ->where('data_type_id', $dataTypeId)
      ->whereIn('slug', $values)
      ->pluck('id')
      ->toArray();
  }

  public function returnEntryIdsFromCollectionItems(array $collectionIds): array
  {
    return DataCollectionItem::whereIn('collection_id', $collectionIds)
      ->pluck('item_id')
      ->unique()
      ->toArray();
  }

  public function pluckEntryIdsByFieldBetween(string $field, array $values): array
  {
    if (count($values) !== 2) {
      return [];
    }

    return DB::table('data_entry_values')
      ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
      ->where('data_type_fields.name', $field)
      ->whereBetween('data_entry_values.value', [$values[0], $values[1]])
      ->pluck('data_entry_values.data_entry_id')
      ->toArray();
  }

  public function pluckNumericFieldValuesByEntryIds(string $field, array $entryIds): array
  {
    if (empty($entryIds)) {
      return [];
    }

    $rows = DB::table('data_entry_values')
      ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
      ->where('data_type_fields.name', $field)
      ->whereIn('data_entry_values.data_entry_id', $entryIds)
      ->select(['data_entry_values.data_entry_id as entry_id', 'data_entry_values.value as value'])
      ->get();

    $out = [];
    foreach ($rows as $row) {
      $out[(int)$row->entry_id] = (float)$row->value;
    }

    return $out;
  }


  public function pluckEntryIdsByFieldComparisonWithin(string $field, string $operator, $value, array $withinEntryIds): array
  {
    if (empty($withinEntryIds)) {
      return [];
    }

    return DB::table('data_entry_values')
      ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
      ->where('data_type_fields.name', $field)
      ->whereIn('data_entry_values.data_entry_id', $withinEntryIds)
      ->where('data_entry_values.value', $operator, $value)
      ->pluck('data_entry_values.data_entry_id')
      ->toArray();
  }

  public function pluckEntryIdsByFieldLikeWithin(string $field, string $pattern, array $withinEntryIds): array
  {
    if (empty($withinEntryIds)) {
      return [];
    }

    return DB::table('data_entry_values')
      ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
      ->where('data_type_fields.name', $field)
      ->whereIn('data_entry_values.data_entry_id', $withinEntryIds)
      ->where('data_entry_values.value', 'LIKE', $pattern)
      ->pluck('data_entry_values.data_entry_id')
      ->toArray();
  }

  public function pluckEntryIdsByFieldInWithin(string $field, array $values, array $withinEntryIds): array
  {
    if (empty($values) || empty($withinEntryIds)) {
      return [];
    }

    return DB::table('data_entry_values')
      ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
      ->where('data_type_fields.name', $field)
      ->whereIn('data_entry_values.data_entry_id', $withinEntryIds)
      ->whereIn('data_entry_values.value', $values)
      ->pluck('data_entry_values.data_entry_id')
      ->toArray();
  }

  public function pluckEntryIdsByFieldBetweenWithin(string $field, array $values, array $withinEntryIds): array
  {
    if (count($values) !== 2 || empty($withinEntryIds)) {
      return [];
    }

    return DB::table('data_entry_values')
      ->join('data_type_fields', 'data_type_fields.id', '=', 'data_entry_values.data_type_field_id')
      ->where('data_type_fields.name', $field)
      ->whereIn('data_entry_values.data_entry_id', $withinEntryIds)
      ->whereBetween('data_entry_values.value', [$values[0], $values[1]])
      ->pluck('data_entry_values.data_entry_id')
      ->toArray();
  }
}
