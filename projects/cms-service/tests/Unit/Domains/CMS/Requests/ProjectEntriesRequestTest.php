<?php

use App\Domains\CMS\Requests\ProjectEntriesRequest;
use Illuminate\Support\Facades\Validator;

test('it passes validation with valid data', function ($data) {
  $request = new ProjectEntriesRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
})->with([
  'all fields' => [['lang' => 'en', 'page' => 1, 'per_page' => 50, 'search' => 'test', 'field_id' => 1, 'date_from' => '2026-01-01', 'date_to' => '2026-01-31']],
  'only optional fields' => [['page' => 2]],
  'empty request' => [[]],
]);

test('it fails validation with invalid data', function ($data) {
  $request = new ProjectEntriesRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'page less than 1' => [['page' => 0]],
  'per_page more than 100' => [['per_page' => 101]],
  'invalid date format' => [['date_from' => 'not-a-date']],
]);

test('getFilters returns correct data with defaults', function () {
  $request = new ProjectEntriesRequest();

  // محاكاة إدخال بيانات جزئية
  $request->merge(['lang' => 'ar']);

  $filters = $request->getFilters();

  // التحقق من القيم المستخرجة والقيم الافتراضية
  expect($filters)->toBe([
    'lang' => 'ar',
    'page' => 1,         // القيمة الافتراضية
    'per_page' => 20,    // القيمة الافتراضية
    'search' => null,
    'field_id' => null,
    'date_from' => null,
    'date_to' => null,
  ]);
});

test('getFilters returns custom values when provided', function () {
  $request = new ProjectEntriesRequest();

  // محاكاة إدخال كامل البيانات
  $request->merge([
    'lang' => 'fr',
    'page' => 5,
    'per_page' => 30,
    'search' => 'laravel',
    'field_id' => 10
  ]);

  $filters = $request->getFilters();

  expect($filters['page'])->toBe(5)
    ->and($filters['per_page'])->toBe(30)
    ->and($filters['lang'])->toBe('fr')
    ->and($filters['search'])->toBe('laravel');
});
