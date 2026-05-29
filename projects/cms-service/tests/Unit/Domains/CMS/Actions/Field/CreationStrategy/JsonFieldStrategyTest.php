<?php

namespace Tests\Unit\Domains\CMS\Actions\Field\CreationStrategy;

use App\Domains\CMS\Actions\Field\CreationStrategy\JsonFieldStrategy;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
  $this->strategy = new JsonFieldStrategy();
});

test('it validates allowed rules correctly', function () {
  // القواعد المسموح بها في الكلاس هي: json, required, nullable
  $rules = ['required', 'json', 'nullable'];

  expect(fn() => $this->strategy->validateRules($rules))->not->toThrow(HttpException::class);
});

test('it throws 422 exception if an invalid rule is provided', function () {
  // 'min:5' غير مسموح بها في الـ JsonFieldStrategy
  $rules = ['required', 'min:5'];

  expect(fn() => $this->strategy->validateRules($rules))
    ->toThrow(HttpException::class, "Rule 'min:5' is not allowed for JSON field.");
});

test('it returns null for schema when no settings provided', function () {
  $settings = [];
  $normalized = $this->strategy->normalizeSettings($settings);

  expect($normalized['schema'])->toBeNull();
});

test('it keeps schema value when provided in settings', function () {
  $customSettings = ['schema' => '{"type": "object"}'];

  $normalized = $this->strategy->normalizeSettings($customSettings);

  expect($normalized['schema'])->toBe('{"type": "object"}');
});
