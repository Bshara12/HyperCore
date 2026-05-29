<?php

namespace Tests\Unit\Domains\CMS\Read\Requests;

use App\Domains\CMS\Read\Requests\EntryVersionsRequest;
use Illuminate\Support\Facades\Validator;

test('it validates request data correctly', function (array $data, bool $shouldPass) {
  $request = new EntryVersionsRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid data' => [['page' => 1, 'per_page' => 50, 'with_snapshot' => true], true],
  'invalid page' => [['page' => 'abc'], false],
  'page too low' => [['page' => 0], false],
  'per_page exceeds max' => [['per_page' => 201], false],
  'invalid boolean' => [['with_snapshot' => 'not-a-bool'], false],
]);

test('it returns correct values from helper methods', function () {
  // محاكاة الطلب (Request) مع بيانات كويري
  $request = EntryVersionsRequest::create('/?page=5&per_page=100&with_snapshot=1', 'GET');

  expect($request->page())->toBe(5)
    ->and($request->perPage())->toBe(100)
    ->and($request->withSnapshot())->toBeTrue();
});

test('it returns default values when parameters are missing', function () {
  $request = EntryVersionsRequest::create('/', 'GET');

  expect($request->page())->toBe(1)      // القيمة الافتراضية المحددة في الكود
    ->and($request->perPage())->toBe(20)   // القيمة الافتراضية
    ->and($request->withSnapshot())->toBeFalse(); // القيمة الافتراضية
});
