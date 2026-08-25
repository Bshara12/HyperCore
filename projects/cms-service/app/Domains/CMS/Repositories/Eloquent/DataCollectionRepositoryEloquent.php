<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO;
use App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Models\DataCollection;
use App\Models\DataCollectionItem;
use App\Models\DataEntry;
use App\Support\CurrentProject;
use DomainException;

class DataCollectionRepositoryEloquent implements DataCollectionRepositoryInterface
{
  // public function getBySlug(string $slug): ?DataCollection
  // {
  //   return DataCollection::where('slug', $slug)->first();
  // }
  public function getBySlug(string $slug): ?DataCollection
  {
    return DataCollection::where('project_id', CurrentProject::id())
      ->where('slug', $slug)
      ->first();
  }


  public function create($dto): DataCollection
  {
    return DataCollection::create($dto->CollectionToArray());
  }

  public function createDataCollectionItem(array $data): void
  {
    DataCollectionItem::create($data);
  }

  public function update(UpdateDataCollectionDTO $dto): DataCollection
  {
    $collection = DataCollection::findOrFail($dto->collection_id);
    $collection->update($dto->toArray());

    return $collection;
  }

  public function delete(int $collectionId): void
  {
    DataCollection::findOrFail($collectionId)->delete();
  }

  public function deleteItems(int $collectionId): void
  {
    DataCollectionItem::where('collection_id', $collectionId)->delete();
  }

  /*
   | Read paths hide inactive collections by default. Deactivating a collection
   | is meant to take it out of circulation, and before this the flag was stored
   | but never enforced, so a "deactivated" offer stayed fully readable.
   |
   | $includeInactive is for callers who manage collections (they need to see an
   | inactive one to reactivate it); the controller derives it from permissions.
   | Write paths keep using getBySlug() and are unaffected — you must be able to
   | edit a collection you just deactivated.
   */

  public function list(int $projectId, bool $includeInactive = false)
  {
    return DataCollection::where('project_id', $projectId)
      ->unless($includeInactive, fn($query) => $query->where('is_active', true))
      ->get();
  }

  public function find(int $projectId, string $slug, bool $includeInactive = false): ?DataCollection
  {
    return DataCollection::where('project_id', $projectId)
      ->where('slug', $slug)
      ->unless($includeInactive, fn($query) => $query->where('is_active', true))
      ->first();
  }

  public function findById(int $collectionId, bool $includeInactive = false): ?DataCollection
  {
    return DataCollection::where('id', $collectionId)
      ->unless($includeInactive, fn($query) => $query->where('is_active', true))
      ->first();
  }

  public function getCollectionItems(int $collectionId)
  {
    // Eager loaded: one query for the items, one for the entries, one for the
    // values — instead of two extra queries per item.
    $items = DataCollectionItem::with('entry.values')
      ->where('collection_id', $collectionId)
      ->orderBy('sort_order')
      ->get();

    foreach ($items as $item) {
      $item->setRelation('data', $item->entry);
      $item->makeHidden('entry');
    }

    return $items;
  }

  /**
   * Replace a collection's items in one go — used to (re)generate a dynamic
   * collection. Delete + insert together so a regeneration is atomic and can be
   * retried: the caller wraps this in a transaction, so there is never a moment
   * where the collection is visibly empty.
   *
   * @param  int[]  $entryIds
   */
  public function replaceItems(int $collectionId, array $entryIds): void
  {
    DataCollectionItem::where('collection_id', $collectionId)->delete();

    if (empty($entryIds)) {
      return;
    }

    $now = now();
    $sortOrder = 1;
    $rows = [];

    foreach ($entryIds as $entryId) {
      $rows[] = [
        'collection_id' => $collectionId,
        'item_id' => (int) $entryId,
        'sort_order' => $sortOrder++,
        'created_at' => $now,
        'updated_at' => $now,
      ];
    }

    // Chunked: a single INSERT with thousands of rows can exceed the driver's
    // placeholder limit.
    foreach (array_chunk($rows, 500) as $chunk) {
      DataCollectionItem::insert($chunk);
    }
  }

  public function insertItems(int $collectionId, array $items): void
  {
    $index = (DataCollectionItem::where('collection_id', $collectionId)->max('sort_order') ?? 0) + 1;
    foreach ($items as $item) {

      $exists = DataCollectionItem::where('collection_id', $collectionId)
        ->where('item_id', $item)
        ->exists();

      if ($exists) {
        continue;
      }

      DataCollectionItem::create([
        'collection_id' => $collectionId,
        'item_id' => $item,
        'sort_order' => $index++,
      ]);
    }
  }

  public function removeItems(int $collectionId, array $items): void
  {
    // foreach ($items as $item) {
    //   $record = DataCollectionItem::where('item_id', $item)->first();
    //   if (! $record) {
    //     continue;
    //   }

    //   if ($collectionId != $record->collection_id) {
    //     throw new DomainException("You can't remove items from different collection.");
    //   }

    //   $record->delete();
    // }

    // $remainingItems = DataCollectionItem::where('collection_id', $collectionId)
    //   ->orderBy('sort_order')
    //   ->get();

    // $order = 1;
    // foreach ($remainingItems as $item) {
    //   $item->sort_order = $order++;
    //   $item->save();
    // }

    DataCollectionItem::where('collection_id', $collectionId)
      ->whereIn('item_id', $items)
      ->delete();

    // إعادة الترقيم
    DataCollectionItem::where('collection_id', $collectionId)
      ->orderBy('sort_order')->get()
      ->each(fn($item, $i) => $item->update(['sort_order' => $i + 1]));
  }

  public function pluckCollectionEntryIds(int $collectionId): array
  {
    return DataCollectionItem::query()
      ->where('collection_id', $collectionId)
      ->orderBy('sort_order')
      ->pluck('item_id')
      ->toArray();
  }

  public function reOrderItems($collectionId, $items)
  {
    $currentItems = DataCollectionItem::where('collection_id', $collectionId)
      ->orderBy('sort_order')
      ->get();

    $ordered = $currentItems->pluck('id')->toArray();

    foreach ($items as $item) {
      $id = $item['item_id'];
      $newPos = $item['sort_order'] - 1;

      $oldIndex = array_search($id, $ordered);
      if ($oldIndex !== false) {
        // يحذف عنصر واحد من المصفوفة ordered عند الرقم oldIndex
        array_splice($ordered, $oldIndex, 1);
      }
      // عند المكان index=newPos
      // لا تحذف شيء0
      // أضف هذا العنصر [$id]
      array_splice($ordered, $newPos, 0, [$id]);
    }

    foreach ($ordered as $index => $id) {
      DataCollectionItem::where('id', $id)->update([
        'sort_order' => $index + 1,
      ]);
    }
    $items = DataCollectionItem::where('collection_id', $collectionId)->orderBy('sort_order')->get();
    foreach ($items as $item) {
      $entry = DataEntry::where('id', $item->item_id)->first();
      $item['data'] = $entry ?? null;
      if ($entry) {
        $entry['values'] = $entry->values()->get();
      }
    }

    return $items;
  }

  public function getEntries(int $collectionId)
  {
    $items = DataCollectionItem::where('collection_id', $collectionId)->get();

    $data = [];

    foreach ($items as $item) {
      $entry = DataEntry::find($item->item_id);
      if (! $entry) {
        continue;
      }

      $fieldId = $entry->dataType->fields
        ->where('name', 'price')
        ->pluck('id')
        ->first();

      $price = $entry->values()
        ->where('data_type_field_id', $fieldId)
        ->value('value');

      $data[] = [
        'id' => $entry->id,
        'price' => (float) $price,
      ];
    }

    return $data;
  }

  public function deactivate(DeactivateCollectionDTO $dto): void
  {
    // No is_active filter in the lookup: the endpoint sets the flag to whatever
    // was requested, so it has to be able to find an already-inactive collection
    // in order to reactivate it. Filtering on is_active = true made the "true"
    // direction unreachable and silently reported success.
    $collection = DataCollection::where('project_id', $dto->project_id)
      ->where('slug', $dto->slug)
      ->first();

    if ($collection) {
      $collection->update(['is_active' => $dto->is_active]);
    }
  }
}
