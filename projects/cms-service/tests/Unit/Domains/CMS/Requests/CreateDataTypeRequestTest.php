<?php

use App\Domains\CMS\Requests\CreateDataTypeRequest;

test('it passes with valid data', function () {
  $data = [
    'name' => 'Article',
    'slug' => 'article',
    'description' => 'A valid description',
    'is_active' => true,
    'settings' => ['key' => 'value'],
  ];

  expect(validateRequest($data, CreateDataTypeRequest::class)->passes())->toBeTrue();
});

test('it passes when only required fields are provided', function () {
  $data = [
    'name' => 'Minimal Type',
    'slug' => 'minimal-type',
  ];

  expect(validateRequest($data, CreateDataTypeRequest::class)->passes())->toBeTrue();
});

test('it fails if required fields are missing', function () {
  $data = []; // إرسال بيانات فارغة

  $validator = validateRequest($data, CreateDataTypeRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has(['name', 'slug']))->toBeTrue();
});

test('it validates data types and constraints', function ($data, $field) {
  $validator = validateRequest($data, CreateDataTypeRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has($field))->toBeTrue();
})->with([
  'name too long' => [
    ['name' => str_repeat('a', 256), 'slug' => 'slug'],
    'name'
  ],
  'slug too long' => [
    ['name' => 'Name', 'slug' => str_repeat('a', 256)],
    'slug'
  ],
  'invalid boolean for is_active' => [
    ['name' => 'Name', 'slug' => 'slug', 'is_active' => 'not-a-bool'],
    'is_active'
  ],
  'invalid array for settings' => [
    ['name' => 'Name', 'slug' => 'slug', 'settings' => 'not-an-array'],
    'settings'
  ],
]);
