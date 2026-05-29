<?php

use App\Domains\CMS\Requests\RateRequest;
use Illuminate\Support\Facades\Validator;

test('it passes validation with valid data', function ($data) {
  $request = new RateRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
})->with([
  'valid project rating' => [['rateable_type' => 'project', 'rateable_id' => 1, 'rating' => 5]],
  'valid data rating'    => [['rateable_type' => 'data', 'rateable_id' => 42, 'rating' => 3]],
  'with review'          => [['rateable_type' => 'project', 'rateable_id' => 1, 'rating' => 4, 'review' => 'Excellent']],
  'min rating boundary'  => [['rateable_type' => 'project', 'rateable_id' => 1, 'rating' => 1]],
  'max rating boundary'  => [['rateable_type' => 'project', 'rateable_id' => 1, 'rating' => 5]],
]);

test('it fails validation with invalid data', function ($data) {
  $request = new RateRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'invalid rateable_type'   => [['rateable_type' => 'user', 'rateable_id' => 1, 'rating' => 5]],
  'rating too low'          => [['rateable_type' => 'project', 'rateable_id' => 1, 'rating' => 0]],
  'rating too high'         => [['rateable_type' => 'project', 'rateable_id' => 1, 'rating' => 6]],
  'missing required fields' => [['rateable_type' => 'project']], // missing id and rating
  'non-integer rateable_id' => [['rateable_type' => 'project', 'rateable_id' => 'abc', 'rating' => 3]],
  'non-integer rating'      => [['rateable_type' => 'project', 'rateable_id' => 1, 'rating' => 'good']],
]);
