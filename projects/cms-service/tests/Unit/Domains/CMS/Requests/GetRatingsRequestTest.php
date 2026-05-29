<?php

use App\Domains\CMS\Requests\GetRatingsRequest;
use Illuminate\Support\Facades\Validator;

test('it passes validation with valid data', function ($data) {
  $request = new GetRatingsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
})->with([
  'valid project' => [['rateable_type' => 'project', 'rateable_id' => 1]],
  'valid data' => [['rateable_type' => 'data', 'rateable_id' => 10]],
  'with per_page' => [['rateable_type' => 'project', 'rateable_id' => 1, 'per_page' => 20]],
  'min per_page' => [['rateable_type' => 'project', 'rateable_id' => 1, 'per_page' => 1]],
  'max per_page' => [['rateable_type' => 'project', 'rateable_id' => 1, 'per_page' => 50]],
]);

test('it fails validation with invalid data', function ($data) {
  $request = new GetRatingsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'invalid rateable_type' => [['rateable_type' => 'invalid', 'rateable_id' => 1]],
  'missing rateable_type' => [['rateable_id' => 1]],
  'non-integer rateable_id' => [['rateable_type' => 'project', 'rateable_id' => 'abc']],
  'per_page too low' => [['rateable_type' => 'project', 'rateable_id' => 1, 'per_page' => 0]],
  'per_page too high' => [['rateable_type' => 'project', 'rateable_id' => 1, 'per_page' => 51]],
]);
