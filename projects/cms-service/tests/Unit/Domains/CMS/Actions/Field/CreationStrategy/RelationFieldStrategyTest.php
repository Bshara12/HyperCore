<?php

namespace Tests\Unit\Domains\CMS\Actions\Field\CreationStrategy;

use App\Domains\CMS\Actions\Field\CreationStrategy\RelationFieldStrategy;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
  $this->strategy = new RelationFieldStrategy();
});

test('it validates allowed rules correctly', function () {
  $rules = ['required', 'exists:data_types,id'];

  expect(fn() => $this->strategy->validateRules($rules))->not->toThrow(HttpException::class);
});

test('it throws 422 exception if an invalid rule is provided', function () {
  $rules = ['required', 'numeric']; // 'numeric' غير مسموح به في هذا الكلاس

  expect(fn() => $this->strategy->validateRules($rules))
    ->toThrow(HttpException::class, "Rule 'numeric' is not allowed for relation field.");
});

test('it throws 422 if required fields are missing in settings', function () {
  // محاولة الإرسال بدون relation_type
  expect(fn() => $this->strategy->normalizeSettings(['related_data_type_id' => 1]))
    ->toThrow(HttpException::class, "Relation field requires 'relation_type'.");

  // محاولة الإرسال بدون related_data_type_id
  expect(fn() => $this->strategy->normalizeSettings(['relation_type' => 'belongs_to']))
    ->toThrow(HttpException::class, "Relation field requires 'related_data_type_id'.");
});

test('it sets correct multiple value based on relation type', function () {
  // اختبار belongs_to (يجب أن يكون multiple false)
  $belongsTo = $this->strategy->normalizeSettings([
    'relation_type' => 'belongs_to',
    'related_data_type_id' => 1
  ]);
  expect($belongsTo['multiple'])->toBeFalse();

  // اختبار has_many (يجب أن يكون multiple true)
  $hasMany = $this->strategy->normalizeSettings([
    'relation_type' => 'has_many',
    'related_data_type_id' => 1
  ]);
  expect($hasMany['multiple'])->toBeTrue();

  // اختبار many_to_many (يجب أن يكون multiple true)
  $manyToMany = $this->strategy->normalizeSettings([
    'relation_type' => 'many_to_many',
    'related_data_type_id' => 1
  ]);
  expect($manyToMany['multiple'])->toBeTrue();
});

test('it throws 422 for invalid relation type', function () {
  expect(fn() => $this->strategy->normalizeSettings([
    'relation_type' => 'invalid_type',
    'related_data_type_id' => 1
  ]))->toThrow(HttpException::class, 'Invalid relation_type.');
});

test('it allows overriding multiple value manually', function () {
  $settings = [
    'relation_type' => 'belongs_to', // افتراضياً يكون false
    'related_data_type_id' => 1,
    'multiple' => true // override
  ];

  $normalized = $this->strategy->normalizeSettings($settings);

  expect($normalized['multiple'])->toBeTrue();
});
