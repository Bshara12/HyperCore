<?php

use App\Domains\CMS\Requests\UpdateDataTypeRequest;
use Illuminate\Support\Facades\Validator;

test('it passes validation with valid full data', function () {
  $data = [
    'name' => 'Article Type',
    'slug' => 'article-type',
    'description' => 'A description for this type',
    'is_active' => true,
    'settings' => ['theme' => 'dark'],
  ];

  $request = new UpdateDataTypeRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('it passes validation with minimal required data', function () {
  $data = [
    'name' => 'Article Type',
    'slug' => 'article-type',
  ];

  $request = new UpdateDataTypeRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('it fails validation with invalid data', function ($data) {
  $request = new UpdateDataTypeRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'missing name' => [['slug' => 'test']],
  'missing slug' => [['name' => 'Test']],
  'name is too long' => [['name' => str_repeat('a', 256), 'slug' => 'test']],
  'slug is too long' => [['name' => 'test', 'slug' => str_repeat('a', 256)]],
  'is_active is not boolean' => [['name' => 'test', 'slug' => 'test', 'is_active' => 'not-boolean']],
  'settings is not array' => [['name' => 'test', 'slug' => 'test', 'settings' => 'not-array']],
]);
