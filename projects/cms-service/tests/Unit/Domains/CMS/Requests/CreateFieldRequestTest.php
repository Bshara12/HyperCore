<?php

use App\Domains\CMS\Requests\CreateFieldRequest;
use App\Models\DataType; // افترضت مسار الموديل هنا
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class); // ضروري جداً لكي يعمل validation rule (exists)

test('it passes with valid complete data', function () {
  // نحتاج لإنشاء سجل في جدول data_types ليتم التحقق منه
  $dataType = DataType::factory()->create();

  $data = [
    'name' => 'user_status',
    'type' => 'relation',
    'required' => true,
    'translatable' => false,
    'validation_rules' => ['required', 'string'],
    'settings' => [
      'relation_type' => 'belongs_to',
      'related_data_type_id' => $dataType->id,
      'multiple' => false,
    ],
    'sort_order' => 1,
  ];

  expect(validateRequest($data, CreateFieldRequest::class)->passes())->toBeTrue();
});

test('it passes with only required fields', function () {
  $data = [
    'name' => 'title',
    'type' => 'string',
  ];

  expect(validateRequest($data, CreateFieldRequest::class)->passes())->toBeTrue();
});

test('it fails if required fields are missing', function () {
  $data = []; // حقول فارغة

  $validator = validateRequest($data, CreateFieldRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has(['name', 'type']))->toBeTrue();
});

test('it validates boolean and array structures', function ($field, $value) {
  $data = [
    'name' => 'test',
    'type' => 'text',
    $field => $value
  ];

  $validator = validateRequest($data, CreateFieldRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has($field))->toBeTrue();
})->with([
  'invalid boolean for required' => ['required', 'not-bool'],
  'invalid boolean for translatable' => ['translatable', 'not-bool'],
  'invalid array for validation_rules' => ['validation_rules', 'not-array'],
  'invalid array for settings' => ['settings', 'not-array'],
]);

test('it fails if related_data_type_id does not exist', function () {
  $data = [
    'name' => 'test',
    'type' => 'relation',
    'settings' => [
      'related_data_type_id' => 9999, // ID غير موجود
    ]
  ];

  $validator = validateRequest($data, CreateFieldRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has('settings.related_data_type_id'))->toBeTrue();
});
