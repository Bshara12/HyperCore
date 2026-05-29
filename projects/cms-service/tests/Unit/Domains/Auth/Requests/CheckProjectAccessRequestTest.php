<?php

namespace Tests\Unit\Domains\Auth\Requests;

use App\Domains\Auth\Requests\CheckProjectAccessRequest;
use Illuminate\Support\Facades\Validator;

// ملاحظة: تأكد من إلغاء التعليق عن 'project_key' في الكلاس الأصلي لكي يعمل الاختبار
test('it validates valid request data', function () {
  $request = new CheckProjectAccessRequest();

  $data = [
    'user_id' => 1,
    'project_key' => 'some-project-key',
  ];

  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('it fails when user_id is missing or invalid', function () {
  $request = new CheckProjectAccessRequest();

  // بيانات ناقصة
  $data = ['project_key' => 'some-key'];

  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('user_id'))->toBeTrue();
});

test('it fails when data types are incorrect', function () {
  $request = new CheckProjectAccessRequest();

  // user_id يجب أن يكون رقم
  $data = [
    'user_id' => 'not-an-integer',
    'project_key' => 'some-key'
  ];

  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('user_id'))->toBeTrue();
});
