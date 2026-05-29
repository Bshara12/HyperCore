<?php

use App\Domains\CMS\StrategyCheck\StringFieldValidator;

beforeEach(function () {
  $this->validator = new StringFieldValidator();
});

test('it passes validation for string values', function ($value) {
  $config = ['name' => 'description'];

  // تأكد من أن هذه القيم لا ترمي استثناءً
  expect(fn() => $this->validator->validate($value, $config))->not->toThrow(Exception::class);
})->with([
  'Hello World', // نص عادي
  '',            // نص فارغ
  '123',         // رقم داخل نص
  'true',        // نص يمثل قيمة بوليانية
]);

test('it throws exception for non-string values', function ($value) {
  $config = ['name' => 'username'];

  expect(fn() => $this->validator->validate($value, $config))
    ->toThrow(Exception::class, 'Field username must be string.');
})->with([
  'integer' => 123,
  'float'   => 12.5,
  'boolean' => true,
  'null'    => null,
  'array'   => [['some' => 'data']], // لاحظ كيف نمرر المصفوفة هنا
  'object'  => new stdClass,
]);
