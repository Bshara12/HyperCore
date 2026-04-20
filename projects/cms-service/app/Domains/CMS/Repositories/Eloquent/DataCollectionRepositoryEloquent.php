<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO;
use App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Models\DataCollection;
use App\Models\DataCollectionItem;
use App\Models\DataEntry;
use DomainException;

class DataCollectionRepositoryEloquent implements DataCollectionRepositoryInterface
{
  public function getBySlug(string $slug): ?DataCollection
  {
    return DataCollection::where('slug', $slug)->first();
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

  public function list(int $projectId)
  {
    return DataCollection::where('project_id', $projectId)->get();
  }

  public function find(int $projectId, string $slug): ?DataCollection
  {
    return DataCollection::where('project_id', $projectId)->where('slug', $slug)->first();
  }

  public function findById(int $collectionId): ?DataCollection
  {
    return DataCollection::where('id', $collectionId)->first();
  }

  public function getCollectionItems(int $collectionId)
  {
    $items = DataCollectionItem::where('collection_id', $collectionId)->get();
    foreach ($items as $item) {
      $entry = DataEntry::where('id', $item->item_id)->first();
      $item['data'] = $entry ?? null;
      if ($entry) {
        $entry['values'] = $entry->values()->get();
      }
    }
    return $items;
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
    foreach ($items as $item) {
      $record = DataCollectionItem::where('item_id', $item)->where('collection_id', $collectionId)->first();
      if (!$record) {
        continue;
      }

      if ($collectionId != $record->collection_id) {
        throw new DomainException("You can't remove items from different collection.");
      }

      $record->delete();
    }

    $remainingItems = DataCollectionItem::where('collection_id', $collectionId)
      ->orderBy('sort_order')
      ->get();

    $order = 1;
    foreach ($remainingItems as $item) {
      $item->sort_order = $order++;
      $item->save();
    }
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
        'sort_order' => $index + 1
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
      if (!$entry) {
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
    $collection = DataCollection::where('slug', $dto->slug)->where('project_id', $dto->project_id)->where('is_active', true)->first();
    if ($collection) {
      $collection->update(['is_active' => false]);
    }
  }
}
