<?php

use App\Domains\CMS\Requests\ReOrderCollectionItemsRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  // 1. إنشاء سجلات أصلية (Parent records) لتجنب انتهاك الـ Foreign Key
  $collection = \App\Models\DataCollection::factory()->create();

  // إنشاء 3 سجلات في data_entries
  $entries = \App\Models\DataEntry::factory()->count(3)->create();

  // 2. إدراج البيانات في جدول الـ Pivot مع توفير كافة القيم المطلوبة
  foreach ($entries as $index => $entry) {
    DB::table('data_collection_items')->insert([
      'id' => $index + 1,
      'collection_id' => $collection->id,
      'item_id' => $entry->id,
      'sort_order' => $index + 1, // اختيارياً
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }
});

test('it passes validation with valid item ordering', function () {
  $request = new ReOrderCollectionItemsRequest();
  $data = [
    'items' => [
      ['item_id' => 1, 'sort_order' => 1],
      ['item_id' => 2, 'sort_order' => 2],
    ]
  ];

  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('it fails validation with invalid input structure', function ($data) {
  $request = new ReOrderCollectionItemsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'not an array' => [['items' => 'invalid']],
  'empty items'  => [['items' => []]],
]);

test('it fails validation for item_id and sort_order rules', function ($data) {
  $request = new ReOrderCollectionItemsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'item_id not exists'  => [['items' => [['item_id' => 999, 'sort_order' => 1]]]],
  'duplicate item_id'   => [['items' => [['item_id' => 1, 'sort_order' => 1], ['item_id' => 1, 'sort_order' => 2]]]],
  'duplicate sort_order' => [['items' => [['item_id' => 1, 'sort_order' => 1], ['item_id' => 2, 'sort_order' => 1]]]],
  'sort_order too low'  => [['items' => [['item_id' => 1, 'sort_order' => 0]]]],
]);

test('it returns custom validation messages', function () {
  $request = new ReOrderCollectionItemsRequest();
  $messages = $request->messages();

  // اختبار رسالة items.required
  $validator = Validator::make([], $request->rules(), $messages);
  expect($validator->errors()->first('items'))->toBe('The items field is required.');

  // اختبار رسالة الخطأ لـ item_id غير موجود
  $validator = Validator::make(['items' => [['item_id' => 999]]], $request->rules(), $messages);
  expect($validator->errors()->first('items.0.item_id'))->toBe('One or more item_id values do not exist in the database.');

  // اختبار رسالة الخطأ لتكرار sort_order
  $validator = Validator::make([
    'items' => [
      ['item_id' => 1, 'sort_order' => 1],
      ['item_id' => 2, 'sort_order' => 1]
    ]
  ], $request->rules(), $messages);

  // ملاحظة: قاعدة distinct على sort_order ستفشل، نتحقق من الرسالة
  expect($validator->errors()->first('items.1.sort_order'))->toBe('Duplicate sort_order values are not allowed.');
});
