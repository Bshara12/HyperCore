<?php

use App\Models\DataEntryRelation;
use App\Models\DataEntry;
use App\Models\DataTypeRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can create a data entry relation', function () {
  // 1. التبعيات
  $entry1 = DataEntry::factory()->create();
  $entry2 = DataEntry::factory()->create();
  $typeRelation = DataTypeRelation::factory()->create();

  // 2. الإنشاء
  $relation = DataEntryRelation::create([
    'data_entry_id' => $entry1->id,
    'related_entry_id' => $entry2->id,
    'data_type_relation_id' => $typeRelation->id,
  ]);

  // 3. التحقق
  expect($relation->data_entry_id)->toBe($entry1->id)
    ->and($relation->related_entry_id)->toBe($entry2->id)
    ->and($relation->data_type_relation_id)->toBe($typeRelation->id);
});

test('it has correct relationships', function () {
  $entry1 = DataEntry::factory()->create();
  $entry2 = DataEntry::factory()->create();
  $typeRelation = DataTypeRelation::factory()->create();

  $relation = DataEntryRelation::create([
    'data_entry_id' => $entry1->id,
    'related_entry_id' => $entry2->id,
    'data_type_relation_id' => $typeRelation->id,
  ]);

  expect($relation->entry)->toBeInstanceOf(DataEntry::class)
    ->and($relation->relatedEntry)->toBeInstanceOf(DataEntry::class)
    ->and($relation->dataTypeRelation)->toBeInstanceOf(DataTypeRelation::class);
});
