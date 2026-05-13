<?php

use App\Domains\E_Commerce\DTOs\Offers\ActivationOfferDTO;
use App\Domains\E_Commerce\Requests\ActivationOfferRequest;

it('creates an activation offer dto from slug and request correctly', function () {
  // 1. Arrange
  $slug = 'summer-discount-2026';
  $request = new ActivationOfferRequest();

  // محاكاة إرسال قيمة التفعيل
  $request->merge([
    'is_active' => true
  ]);

  // 2. Act
  $dto = ActivationOfferDTO::fromRequest($slug, $request);

  // 3. Assert
  expect($dto)->toBeInstanceOf(ActivationOfferDTO::class)
    ->and($dto->collectionSlug)->toBe($slug)
    ->and($dto->is_active)->toBeTrue();
});

it('can handle deactivation state correctly', function () {
  // اختبار حالة الإيقاف (is_active = false) لضمان تغطية المنطق البوليني
  $slug = 'expired-offer';
  $request = new ActivationOfferRequest();
  $request->merge(['is_active' => false]);

  $dto = ActivationOfferDTO::fromRequest($slug, $request);

  expect($dto->is_active)->toBeFalse();
});

it('can be instantiated manually via constructor', function () {
  // اختبار الـ constructor مباشرة لضمان تغطية 100%
  $dto = new ActivationOfferDTO('manual-slug', true);

  expect($dto->collectionSlug)->toBe('manual-slug')
    ->and($dto->is_active)->toBeTrue();
});
