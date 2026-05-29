<?php

namespace Tests\Unit\Domains\CMS\Actions\Field\CreationStrategy;

use App\Domains\CMS\Actions\Field\CreationStrategy\NumberFieldStrategy;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
  $this->strategy = new NumberFieldStrategy();
});

test('it validates allowed rules correctly', function () {
  // القواعد المسموح بها: numeric, integer, min, max, required, nullable
  $rules = ['required', 'numeric', 'integer', 'min:0', 'max:100', 'nullable'];

  expect(fn() => $this->strategy->validateRules($rules))->not->toThrow(HttpException::class);
});

test('it throws 422 exception if an invalid rule is provided', function () {
  // 'unique' أو 'email' غير مسموح بها في NumberFieldStrategy
  $rules = ['required', 'unique:users'];

  expect(fn() => $this->strategy->validateRules($rules))
    ->toThrow(HttpException::class, "Rule 'unique:users' is not allowed for number field.");
});

test('it returns default settings when no settings provided', function () {
  $settings = [];
  $normalized = $this->strategy->normalizeSettings($settings);

  expect($normalized['default'])->toBeNull()
    ->and($normalized['step'])->toBe(1);
});

test('it overrides default settings correctly', function () {
  $customSettings = [
    'default' => 10,
    'step' => 0.5
  ];

  $normalized = $this->strategy->normalizeSettings($customSettings);

  expect($normalized['default'])->toBe(10)
    ->and($normalized['step'])->toBe(0.5);
});
