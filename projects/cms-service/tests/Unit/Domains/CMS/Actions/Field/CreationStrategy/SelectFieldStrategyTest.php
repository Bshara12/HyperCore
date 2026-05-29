<?php

namespace Tests\Unit\Domains\CMS\Actions\Field\CreationStrategy;

use App\Domains\CMS\Actions\Field\CreationStrategy\SelectFieldStrategy;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
  $this->strategy = new SelectFieldStrategy();
});

test('it validates allowed rules correctly', function () {
  $rules = ['required', 'in:a,b,c'];

  expect(fn() => $this->strategy->validateRules($rules))->not->toThrow(HttpException::class);
});

test('it throws 422 exception if an invalid rule is provided', function () {
  $rules = ['required', 'numeric']; // غير مسموح

  expect(fn() => $this->strategy->validateRules($rules))
    ->toThrow(HttpException::class, "Rule 'numeric' is not allowed for select field.");
});

test('it throws 422 if options are missing', function () {
  $settings = ['default' => 'a'];

  expect(fn() => $this->strategy->normalizeSettings($settings))
    ->toThrow(HttpException::class, "Select field requires 'options' array.");
});

test('it throws 422 if options are not an array', function () {
  $settings = ['options' => 'not-an-array'];

  expect(fn() => $this->strategy->normalizeSettings($settings))
    ->toThrow(HttpException::class, "Select field requires 'options' array.");
});

test('it normalizes valid settings with defaults', function () {
  $settings = ['options' => ['a', 'b']];
  $normalized = $this->strategy->normalizeSettings($settings);

  expect($normalized['options'])->toBe(['a', 'b'])
    ->and($normalized['default'])->toBeNull()
    ->and($normalized['multiple'])->toBeFalse();
});

test('it normalizes valid settings with custom values', function () {
  $settings = [
    'options' => ['a', 'b'],
    'default' => 'a',
    'multiple' => true
  ];
  $normalized = $this->strategy->normalizeSettings($settings);

  expect($normalized['options'])->toBe(['a', 'b'])
    ->and($normalized['default'])->toBe('a')
    ->and($normalized['multiple'])->toBeTrue();
});
