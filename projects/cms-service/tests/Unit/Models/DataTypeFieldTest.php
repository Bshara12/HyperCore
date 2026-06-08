<?php

use App\Models\DataTypeField;
use App\Models\DataType;
use App\Models\DataEntryValue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it covers all relationships correctly', function () {
  $field = DataTypeField::factory()->create();

  // اختبار العلاقة مع DataType
  expect($field->dataType())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
    ->and($field->dataType)->toBeInstanceOf(DataType::class);

  // اختبار العلاقة مع القيم (Values)
  DataEntryValue::factory()->create(['data_type_field_id' => $field->id]);
  expect($field->values)->toHaveCount(1)
    ->and($field->values->first())->toBeInstanceOf(DataEntryValue::class);
});

test('it casts attributes correctly', function () {
  $field = DataTypeField::factory()->create([
    'validation_rules' => ['max' => 255],
    'settings'         => ['key' => 'value'],
    'required'         => 1,
    'translatable'     => 0,
    'sort_order'       => '5'
  ]);

  expect($field->validation_rules)->toBe(['max' => 255])
    ->and($field->settings)->toBe(['key' => 'value'])
    ->and($field->required)->toBeTrue()
    ->and($field->translatable)->toBeFalse()
    ->and($field->sort_order)->toBe(5);
});

test('it supports soft deletes', function () {
  $field = DataTypeField::factory()->create();
  $field->delete();

  expect($field->trashed())->toBeTrue();
});
