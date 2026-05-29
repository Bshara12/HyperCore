<?php

use App\Domains\CMS\Requests\DeactivateCollectionRequest;
use Illuminate\Support\Facades\Validator;

test('it passes validation with valid boolean data', function ($data) {
  $request = new DeactivateCollectionRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
})->with([
  'active true' => [['is_active' => true]],
  'active false' => [['is_active' => false]],
  'active string true' => [['is_active' => '1']], // لارافل تقبل هذا كـ boolean
  'active string false' => [['is_active' => '0']],
]);

test('it fails validation with invalid data', function ($data) {
  $request = new DeactivateCollectionRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('is_active'))->toBeTrue();
})->with([
  'null value' => [['is_active' => null]],
  'string value' => [['is_active' => 'hello']],
  'array value' => [['is_active' => []]],
  'missing field' => [[]],
]);
