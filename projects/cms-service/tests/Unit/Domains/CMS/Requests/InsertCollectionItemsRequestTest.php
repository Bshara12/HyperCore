<?php

use App\Domains\CMS\Requests\InsertCollectionItemsRequest;
use App\Models\DataEntry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it passes validation with valid item IDs', function () {
  $entry1 = DataEntry::factory()->create();
  $entry2 = DataEntry::factory()->create();

  $request = new InsertCollectionItemsRequest();
  $data = ['items' => [$entry1->id, $entry2->id]];

  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('it fails if items is not an array or is empty', function ($data) {
  $request = new InsertCollectionItemsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'not an array' => [['items' => 'not-an-array']],
  'empty array' => [['items' => []]],
]);

test('it fails if item IDs are not integers or do not exist', function ($data) {
  $request = new InsertCollectionItemsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'non-integer' => [['items' => ['abc']]],
  'non-existent ID' => [['items' => [999]]],
]);

test('it fails if item IDs are not distinct', function () {
  $entry = DataEntry::factory()->create();

  $request = new InsertCollectionItemsRequest();
  $data = ['items' => [$entry->id, $entry->id]]; // مكرر

  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('items.1'))->toBeTrue();
});

test('it returns custom validation messages', function () {
  $request = new InsertCollectionItemsRequest();
  $messages = $request->messages(); // استدعاء الرسائل من الكلاس

  // 1. اختبار رسالة الخطأ عندما يكون المدخل ليس مصفوفة (items.array)
  $validator = Validator::make(['items' => 'string'], $request->rules(), $messages);
  expect($validator->errors()->first('items'))->toBe('The items field must be an array.');

  // 2. اختبار رسالة الخطأ لـ item_id غير صحيح (items.*.integer)
  $validator = Validator::make(['items' => ['invalid-id']], $request->rules(), $messages);
  expect($validator->errors()->first('items.0'))->toBe('The item_id must be a valid integer.');

  // 3. اختبار رسالة التكرار (items.*.distinct)
  $validator = Validator::make(['items' => [1, 1]], $request->rules(), $messages);
  expect($validator->errors()->first('items.1'))->toBe('Duplicate item_id values are not allowed.');

  // 4. اختبار رسالة الخطأ عند عدم وجود العنصر (items.*.exists)
  $validator = Validator::make(['items' => [99999]], $request->rules(), $messages);
  expect($validator->errors()->first('items.0'))->toBe('One or more item_id values do not exist in the database.');
});
