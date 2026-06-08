<?php

use App\Domains\CMS\Requests\CreateDataCollectionRequest;
use Illuminate\Support\Facades\Validator;

test('it passes with valid data', function () {
  $data = [
    'name' => 'My Collection',
    'slug' => 'my-collection',
    'type' => 'manual',
    'conditions' => [
      ['field' => 'status', 'operator' => '=', 'value' => 'published']
    ],
    'conditions_logic' => 'and',
    'description' => 'A test collection',
    'is_active' => true,
    'settings' => ['key' => 'value'],
  ];

$validator = validateRequest($data, CreateDataCollectionRequest::class);  expect($validator->passes())->toBeTrue();
});

test('it fails when required fields are missing', function () {
  $validator = validateRequest([], CreateDataCollectionRequest::class); // إرسال بيانات فارغة

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has(['name', 'slug', 'type']))->toBeTrue();
});

test('it validates enum constraints for type and logic', function ($key, $value, $shouldPass) {
  $data = [
    'name' => 'Test',
    'slug' => 'test',
    'type' => 'manual',
    $key => $value
  ];

  $validator = validateRequest($data, CreateDataCollectionRequest::class);

  if ($shouldPass) {
    expect($validator->passes())->toBeTrue();
  } else {
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has($key))->toBeTrue();
  }
})->with([
  ['type', 'invalid_type', false], // نوع غير صحيح
  ['conditions_logic', 'invalid_logic', false], // منطق غير صحيح
  ['conditions_logic', 'or', true], // منطق صحيح
]);

test('it validates conditional rules for conditions array', function () {
  // محاولة إرسال الـ conditions بدون الحقول المطلوبة
  $data = [
    'name' => 'Test',
    'slug' => 'test',
    'type' => 'dynamic',
    'conditions' => [
      ['field' => 'status'] // ناقص operator و value
    ]
  ];

  $validator = validateRequest($data, CreateDataCollectionRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has([
    'conditions.0.operator',
    'conditions.0.value'
  ]))->toBeTrue();
});

test('it validates boolean type for is_active', function () {
  $data = [
    'name' => 'Test',
    'slug' => 'test',
    'type' => 'manual',
    'is_active' => 'not-a-boolean'
  ];

  $validator = validateRequest($data, CreateDataCollectionRequest::class);
  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has('is_active'))->toBeTrue();
});
