<?php

use App\Domains\CMS\StrategyCheck\FieldValidatorResolver;
use App\Domains\CMS\StrategyCheck\NumberFieldValidator;
use App\Domains\CMS\StrategyCheck\StringFieldValidator;
use App\Domains\CMS\StrategyCheck\FileFieldValidator;

beforeEach(function () {
  $this->resolver = new FieldValidatorResolver();
});

// 1. اختبار الربط الأساسي للكلاسات
test('it resolves correct validator for standard types', function ($type, $expectedClass) {
  $validator = $this->resolver->resolve($type);
  expect($validator)->toBeInstanceOf($expectedClass);
})->with([
  ['number', NumberFieldValidator::class],
  ['string', StringFieldValidator::class],
  ['text', StringFieldValidator::class],
  ['textarea', StringFieldValidator::class],
  ['select', StringFieldValidator::class],
  ['relation', StringFieldValidator::class],
  ['file', FileFieldValidator::class],
]);

// 2. اختبار منطق الـ JSON (الكلاس المجهول)
test('it validates json field correctly', function () {
  $validator = $this->resolver->resolve('json');

  // يجب ألا يرمي استثناءً عند تمرير مصفوفة أو JSON صالح
  expect(fn() => $validator->validate(['key' => 'value'], []))->not->toThrow(Exception::class);
  expect(fn() => $validator->validate('{"key": "value"}', []))->not->toThrow(Exception::class);

  // يجب أن يرمي استثناءً عند تمرير نص غير صالح
  expect(fn() => $validator->validate('invalid-json', ['name' => 'my_field']))
    ->toThrow(Exception::class, 'Field my_field must be valid JSON.');
});

// 3. اختبار منطق الـ Boolean (الكلاس المجهول)
test('it validates boolean field correctly', function () {
  $validator = $this->resolver->resolve('boolean');

  // قيم صحيحة
  expect(fn() => $validator->validate(true, []))->not->toThrow(Exception::class);
  expect(fn() => $validator->validate(1, []))->not->toThrow(Exception::class);
  expect(fn() => $validator->validate('true', []))->not->toThrow(Exception::class);
  expect(fn() => $validator->validate('0', []))->not->toThrow(Exception::class);

  // قيم خاطئة
  expect(fn() => $validator->validate('invalid', ['name' => 'is_active']))
    ->toThrow(Exception::class, 'Field is_active must be boolean.');
});

// 4. اختبار الحالة الافتراضية
test('it throws exception for unsupported types', function () {
  expect(fn() => $this->resolver->resolve('unknown_type'))
    ->toThrow(Exception::class, 'Unsupported field type: unknown_type');
});
