<?php

use App\Domains\CMS\Requests\GetRatingStatsRequest;
use Illuminate\Support\Facades\Validator;

test('it passes validation with valid data', function ($data) {
  $request = new GetRatingStatsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
})->with([
  'project type' => [['rateable_type' => 'project', 'rateable_id' => 1]],
  'data type'    => [['rateable_type' => 'data', 'rateable_id' => 42]],
]);

test('it fails validation with invalid data', function ($data) {
  $request = new GetRatingStatsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'missing rateable_type'   => [['rateable_id' => 1]],
  'missing rateable_id'     => [['rateable_type' => 'project']],
  'invalid rateable_type'   => [['rateable_type' => 'invalid', 'rateable_id' => 1]],
  'non-integer rateable_id' => [['rateable_type' => 'project', 'rateable_id' => 'abc']],
]);
