<?php

namespace Tests\Unit\Domains\CMS\Actions\Field\CreationStrategy;

use App\Domains\CMS\Actions\Field\CreationStrategy\FileFieldStrategy;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
  $this->strategy = new FileFieldStrategy();
});

test('it validates allowed rules correctly', function () {
  // هذه القواعد مسموح بها
  $rules = ['required', 'nullable', 'mimes:jpg,png', 'max:2048', 'min:10'];

  // لن يرمي أي استثناء
  expect(fn() => $this->strategy->validateRules($rules))->not->toThrow(HttpException::class);
});

test('it throws 422 exception if an invalid rule is provided', function () {
  // 'unique' غير موجود في قائمة allowedRules
  $rules = ['required', 'unique:users'];

  expect(fn() => $this->strategy->validateRules($rules))
    ->toThrow(HttpException::class, "Rule 'unique:users' is not allowed for file field.");
});

test('it returns default settings when empty settings provided', function () {
  $settings = [];
  $normalized = $this->strategy->normalizeSettings($settings);

  expect($normalized['multiple'])->toBeFalse()
    ->and($normalized['max_size'])->toBe(20480)
    ->and($normalized['allowed_types'])->toContain('jpg', 'pdf');
});

test('it overrides default settings correctly', function () {
  $customSettings = [
    'multiple' => true,
    'max_size' => 5000,
    'allowed_types' => ['zip']
  ];

  $normalized = $this->strategy->normalizeSettings($customSettings);

  expect($normalized['multiple'])->toBeTrue()
    ->and($normalized['max_size'])->toBe(5000)
    ->and($normalized['allowed_types'])->toBe(['zip']);
});
