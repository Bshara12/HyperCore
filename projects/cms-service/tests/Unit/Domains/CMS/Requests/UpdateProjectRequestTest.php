<?php

use App\Domains\CMS\Requests\UpdateProjectRequest;
use Illuminate\Support\Facades\Validator;

test('it passes validation with valid full data', function () {
  $data = [
    'name' => 'Project Name',
    'supported_languages' => ['en', 'ar'],
    'enabled_modules' => ['cms', 'billing'],
  ];

  $request = new UpdateProjectRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('it passes with empty data', function () {
  // لأن جميع القواعد تبدأ بـ sometimes، الطلب الفارغ يجب أن يمر
  $request = new UpdateProjectRequest();
  $validator = Validator::make([], $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('it fails validation with invalid data', function ($data) {
  $request = new UpdateProjectRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'name is too long' => [['name' => str_repeat('a', 256)]],
  'name is empty string' => [['name' => '']], // because of 'required'
  'supported_languages is string' => [['supported_languages' => 'en,ar']],
  'enabled_modules is string' => [['enabled_modules' => 'module1']],
]);
