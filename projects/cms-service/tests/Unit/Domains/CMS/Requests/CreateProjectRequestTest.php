<?php

use App\Domains\CMS\Requests\CreateProjectRequest;

test('it passes with valid data', function () {
  $data = [
    'name' => 'New Awesome Project',
    'supported_languages' => ['en', 'ar'],
    'enabled_modules' => ['cms', 'booking'],
  ];

  expect(validateRequest($data, CreateProjectRequest::class)->passes())->toBeTrue();
});

test('it passes with only required name field', function () {
  $data = ['name' => 'Minimal Project'];

  expect(validateRequest($data, CreateProjectRequest::class)->passes())->toBeTrue();
});

test('it fails if name is missing', function () {
  $data = ['supported_languages' => ['en']]; // بدون name

  $validator = validateRequest($data, CreateProjectRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has('name'))->toBeTrue();
});

test('it fails if name is too long', function () {
  $data = ['name' => str_repeat('a', 256)];

  $validator = validateRequest($data, CreateProjectRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has('name'))->toBeTrue();
});

test('it fails if types are incorrect', function ($field, $value) {
  $data = [
    'name' => 'Project Name',
    $field => $value
  ];

  $validator = validateRequest($data, CreateProjectRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has($field))->toBeTrue();
})->with([
  'languages must be array' => ['supported_languages', 'not-an-array'],
  'modules must be array'   => ['enabled_modules', 'not-an-array'],
]);
