<?php

use App\Domains\E_Commerce\DTOs\Offers\UpdateOfferDTO;
use App\Domains\E_Commerce\Requests\UpdateOfferRequest;
use Illuminate\Support\Str;

it('maps all request fields to collection and offer data correctly', function () {
  // 1. Arrange: إرسال جميع الحقول الممكنة
  $slug = 'old-offer-slug';
  $request = new UpdateOfferRequest();
  $request->merge([
    'name' => 'Updated Offer Name',
    'conditions' => ['min_amount' => 100],
    'conditions_logic' => 'or',
    'description' => 'New description',
    'offer_duration' => 60,
    'benefit_type' => 'fixed',
    'benefit_config' => ['amount' => 50],
    'start_at' => '2026-06-01',
    'end_at' => '2026-07-01',
  ]);

  // 2. Act
  $dto = UpdateOfferDTO::fromRequest($slug, $request);

  // 3. Assert
  // فحص بيانات الـ Collection
  expect($dto->collectionData)->toHaveKey('name', 'Updated Offer Name')
    ->and($dto->collectionData)->toHaveKey('slug', 'updated-offer-name')
    ->and($dto->collectionData)->toHaveKey('conditions_logic', 'or');

  // فحص بيانات الـ Offer
  expect($dto->offerData)->toHaveKey('offer_duration', 60)
    ->and($dto->offerData)->toHaveKey('benefit_type', 'fixed')
    ->and($dto->offerData)->toHaveKey('start_at', '2026-06-01');

  expect($dto->collectionSlug)->toBe($slug);
});

it('only includes present fields in the dto arrays', function () {
  // 1. Arrange: إرسال حقول قليلة فقط لاختبار الـ Logic
  $slug = 'minimal-update';
  $request = new UpdateOfferRequest();
  $request->merge([
    'description' => 'Only updating description',
    'end_at' => '2026-12-31'
  ]);

  // 2. Act
  $dto = UpdateOfferDTO::fromRequest($slug, $request);

  // 3. Assert
  // يجب أن تحتوي المصفوفة على الوصف فقط
  expect($dto->collectionData)->toEqual(['description' => 'Only updating description'])
    ->and($dto->collectionData)->not->toHaveKey('name')
    ->and($dto->collectionData)->not->toHaveKey('slug');

  // يجب أن تحتوي المصفوفة على تاريخ الانتهاء فقط
  expect($dto->offerData)->toEqual(['end_at' => '2026-12-31'])
    ->and($dto->offerData)->not->toHaveKey('benefit_type');
});

it('can be instantiated via constructor', function () {
  $dto = new UpdateOfferDTO('slug', ['c' => 1], ['o' => 2]);

  expect($dto->collectionSlug)->toBe('slug')
    ->and($dto->collectionData)->toBe(['c' => 1])
    ->and($dto->offerData)->toBe(['o' => 2]);
});
