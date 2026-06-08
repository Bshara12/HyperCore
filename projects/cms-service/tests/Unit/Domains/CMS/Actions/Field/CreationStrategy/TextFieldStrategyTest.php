<?php

namespace Tests\Unit\Domains\CMS\Actions\Field\CreationStrategy;

use App\Domains\CMS\Actions\Field\CreationStrategy\TextFieldStrategy;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
  $this->strategy = new TextFieldStrategy();
});

test('it validates allowed rules correctly', function () {
  // نختبر مجموعة متنوعة من القواعد المسموحة
  $rules = ['string', 'email', 'max:255', 'unique:users,email', 'required', 'starts_with:http'];

  expect(fn() => $this->strategy->validateRules($rules))->not->toThrow(HttpException::class);
});

test('it throws 422 exception if an invalid rule is provided', function () {
  // نختبر قاعدة غير موجودة في الـ allowedRules
  $rules = ['string', 'not-a-valid-rule'];

  expect(fn() => $this->strategy->validateRules($rules))
    ->toThrow(HttpException::class, "Rule 'not-a-valid-rule' is not allowed for text field.");
});

test('it returns null defaults when no settings provided', function () {
  $settings = [];
  $normalized = $this->strategy->normalizeSettings($settings);

  expect($normalized['placeholder'])->toBeNull()
    ->and($normalized['default'])->toBeNull();
});

test('it normalizes provided settings correctly', function () {
  $settings = [
    'placeholder' => 'Enter your name',
    'default' => 'John Doe'
  ];

  $normalized = $this->strategy->normalizeSettings($settings);

  expect($normalized['placeholder'])->toBe('Enter your name')
    ->and($normalized['default'])->toBe('John Doe');
});
