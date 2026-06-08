<?php

use App\Models\DataCollectionItem;
use App\Models\DataCollection;
use App\Models\DataEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can create a data collection item with all attributes', function () {
  // 1. إنشاء التبعيات المطلوبة
  $collection = DataCollection::factory()->create();
  $entry = DataEntry::factory()->create();

  // 2. إنشاء العنصر
  $item = DataCollectionItem::create([
    'collection_id' => $collection->id,
    'item_id' => $entry->id,
    'sort_order' => 10,
  ]);

  // 3. التحقق من القيم
  expect($item->sort_order)->toBe(10)
    ->and($item->collection_id)->toBe($collection->id)
    ->and($item->item_id)->toBe($entry->id);
});

test('it has relationships to collection and entry', function () {
  $collection = DataCollection::factory()->create();
  $entry = DataEntry::factory()->create();

  $item = DataCollectionItem::factory()->create([
    'collection_id' => $collection->id,
    'item_id' => $entry->id,
  ]);

  // اختبار العلاقة مع المحتوى
  expect($item->collection)->toBeInstanceOf(DataCollection::class)
    ->and($item->collection->id)->toBe($collection->id);

  // اختبار العلاقة مع المدخل الفعلي
  expect($item->entry)->toBeInstanceOf(DataEntry::class)
    ->and($item->entry->id)->toBe($entry->id);
});
