<?php

use App\Domains\E_Commerce\DTOs\Offers\CreateOfferDTO;
use App\Domains\E_Commerce\Requests\CreateOfferRequest;
use Illuminate\Support\Str;

it('creates a dto from request and generates slug correctly', function () {
  // 1. Arrange
  $request = new CreateOfferRequest();
  $request->merge([
    'project_id' => 1,
    'data_type_id' => 10,
    'name' => 'Summer Sale 2026',
    'type' => 'discount',
    'is_code_offer' => true,
    'benefit_type' => 'percentage',
    'benefit_config' => ['value' => 20],
    // نترك الحقول الاختيارية لاختبار الـ Default values
  ]);

  // 2. Act
  $dto = CreateOfferDTO::fromRequest($request);

  // 3. Assert
  expect($dto->name)->toBe('Summer Sale 2026')
    ->and($dto->slug)->toBe('summer-sale-2026') // التأكد من عمل Str::slug
    ->and($dto->conditions_logic)->toBe('and') // التأكد من القيمة الافتراضية
    ->and($dto->is_active)->toBeTrue(); // التأكد من القيمة الافتراضية
});

it('transforms to collection array correctly', function () {
  $dto = new CreateOfferDTO(
    project_id: 1,
    data_type_id: 2,
    name: 'Offer',
    slug: 'offer',
    type: 'type',
    conditions: ['x' => 1],
    conditions_logic: 'or',
    description: 'desc',
    settings: ['s' => 1],
    is_code_offer: false,
    offer_duration: null,
    benefit_type: 'fix',
    benefit_config: [],
    start_at: null,
    end_at: null,
    is_active: true
  );

  $array = $dto->CollectionToArray();

  expect($array)->toBeArray()
    ->and($array['project_id'])->toBe(1)
    ->and($array['conditions_logic'])->toBe('or')
    ->and($array['is_offer'])->toBeTrue();
});

it('generates a random code when is_code_offer is true in OfferToArray', function () {
  $dto = new CreateOfferDTO(
    project_id: 1,
    data_type_id: 2,
    name: 'Offer',
    slug: 'offer',
    type: 'type',
    conditions: null,
    conditions_logic: 'and',
    description: null,
    settings: null,
    is_code_offer: true,
    offer_duration: 30,
    benefit_type: 'percentage',
    benefit_config: [],
    start_at: '2026-01-01',
    end_at: '2026-02-01',
    is_active: true
  );

  $array = $dto->OfferToArray();

  expect($array['is_code_offer'])->toBeTrue()
    ->and($array['code'])->not->toBeNull()
    ->and(strlen($array['code']))->toBe(8) // التأكد من طول الكود العشوائي
    ->and($array['offer_duration'])->toBe(30);
});

it('returns null for code when is_code_offer is false in OfferToArray', function () {
  $dto = new CreateOfferDTO(
    project_id: 1,
    data_type_id: 2,
    name: 'Offer',
    slug: 'offer',
    type: 'type',
    conditions: null,
    conditions_logic: 'and',
    description: null,
    settings: null,
    is_code_offer: false,
    offer_duration: null,
    benefit_type: 'percentage',
    benefit_config: [],
    start_at: null,
    end_at: null,
    is_active: false
  );

  $array = $dto->OfferToArray();

  expect($array['is_code_offer'])->toBeFalse()
    ->and($array['code'])->toBeNull()
    ->and($array['is_active'])->toBeFalse();
});
