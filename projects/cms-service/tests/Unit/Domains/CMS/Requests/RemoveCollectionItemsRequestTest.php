<?php

use App\Domains\CMS\Requests\RemoveCollectionItemsRequest;
use App\Models\DataEntry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it passes validation with valid and distinct item IDs', function () {
  // إنشاء بيانات تجريبية
  $entry1 = DataEntry::factory()->create();
  $entry2 = DataEntry::factory()->create();

  $request = new RemoveCollectionItemsRequest();
  $data = ['items' => [$entry1->id, $entry2->id]];

  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('it fails validation with invalid input format', function ($data) {
  $request = new RemoveCollectionItemsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'not an array' => [['items' => '1,2,3']],
  'empty array' => [['items' => []]],
  'null items' => [['items' => null]],
]);

test('it fails validation for invalid items within array', function ($data) {
  $request = new RemoveCollectionItemsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'non-integer ID' => [['items' => ['abc']]],
  'duplicate IDs' => [['items' => [1, 1]]], // يختبر الـ distinct
  'non-existent ID' => [['items' => [9999]]], // يختبر الـ exists
]);

test('it returns correct custom validation messages', function () {
  $request = new RemoveCollectionItemsRequest();
  $messages = $request->messages();

  // 1. اختبار رسالة الخطأ عند إرسال نص بدلاً من مصفوفة
  $validator = Validator::make(['items' => 'string'], $request->rules(), $messages);
  expect($validator->errors()->first('items'))->toBe('The items field must be an array.');

  // 2. اختبار رسالة الخطأ عند إرسال معرف غير موجود
  $validator = Validator::make(['items' => [9999]], $request->rules(), $messages);
  expect($validator->errors()->first('items.0'))->toBe('One or more item_id values do not exist in the database.');

  // 3. اختبار رسالة الخطأ عند تكرار المعرفات
  $validator = Validator::make(['items' => [1, 1]], $request->rules(), $messages);
  expect($validator->errors()->first('items.1'))->toBe('Duplicate item_id values are not allowed.');
});
