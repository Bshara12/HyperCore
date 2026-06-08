<?php

use App\Domains\CMS\StrategyCheck\NumberFieldValidator;

beforeEach(function () {
  $this->validator = new NumberFieldValidator();
});

test('it passes validation for valid numeric values', function ($value) {
  $config = ['name' => 'quantity'];

  expect(fn() => $this->validator->validate($value, $config))->not->toThrow(Exception::class);
})->with([
  10,          // integer
  25.5,        // float
  '100',       // numeric string
  '10.5',      // numeric string (float)
]);

test('it throws exception for non-numeric values', function () {
  $config = ['name' => 'age'];
  $invalidValue = 'not-a-number';

  expect(fn() => $this->validator->validate($invalidValue, $config))
    ->toThrow(Exception::class, 'Field age must be numeric.');
});

test('it throws exception for array or object', function () {
  $config = ['name' => 'score'];

  expect(fn() => $this->validator->validate(['a'], $config))
    ->toThrow(Exception::class, 'Field score must be numeric.');
});
