<?php

use App\Models\DataEntryValue;
use App\Models\DataEntry;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it belongs to entry and field', function () {
  $entry = DataEntry::factory()->create();
  $field = DataTypeField::factory()->create();

  $value = DataEntryValue::factory()->create([
    'data_entry_id' => $entry->id,
    'data_type_field_id' => $field->id
  ]);

  expect($value->entry)->toBeInstanceOf(DataEntry::class)
    ->and($value->field)->toBeInstanceOf(DataTypeField::class)
    ->and($value->entry->id)->toBe($entry->id)
    ->and($value->field->id)->toBe($field->id);
});

test('it supports soft deletes', function () {
  $value = DataEntryValue::factory()->create();

  $value->delete();

  expect($value->deleted_at)->not->toBeNull()
    ->and(DataEntryValue::find($value->id))->toBeNull() // لا يظهر في الاستعلام العادي
    ->and(DataEntryValue::withTrashed()->find($value->id))->not->toBeNull(); // يظهر مع withTrashed
});
