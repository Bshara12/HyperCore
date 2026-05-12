<?php

use App\Domains\E_Commerce\DTOs\Wishlist\UpdateWishlistDTO;

it('creates a dto from array with partial data correctly', function () {
  // 1. Arrange: إرسال بعض الحقول فقط
  $data = [
    'name' => 'Updated List Name',
    'is_default' => 1 // التأكد من الـ casting إلى true
  ];

  // 2. Act
  $dto = UpdateWishlistDTO::fromArray($data);

  // 3. Assert
  expect($dto->name)->toBe('Updated List Name')
    ->and($dto->is_default)->toBeTrue()
    ->and($dto->visibility)->toBeNull()
    ->and($dto->is_shareable)->toBeNull();
});

it('correctly handles all fields in fromArray', function () {
  // اختبار تمرير كافة الحقول بما فيها القيم المنطقية false
  $data = [
    'name' => 'New Name',
    'visibility' => 'public',
    'is_default' => false,
    'is_shareable' => true,
  ];

  $dto = UpdateWishlistDTO::fromArray($data);

  expect($dto->name)->toBe('New Name')
    ->and($dto->visibility)->toBe('public')
    ->and($dto->is_default)->toBeFalse()
    ->and($dto->is_shareable)->toBeTrue();
});

it('filters out null values when converting to array', function () {
  // هذا الاختبار يضمن عمل array_filter بشكل صحيح
  $dto = new UpdateWishlistDTO(name: 'Only Name');

  $array = $dto->toArray();

  // يجب أن تحتوي المصفوفة على مفتاح الاسم فقط
  expect($array)->toBeArray()
    ->and($array)->toHaveCount(1)
    ->and($array)->toEqual(['name' => 'Only Name'])
    ->and($array)->not->toHaveKey('visibility');
});

it('keeps boolean false values in toArray', function () {
  // من المهم التأكد أن array_filter لا يحذف false، بل يحذف null فقط
  $dto = new UpdateWishlistDTO(is_default: false);

  $array = $dto->toArray();

  expect($array)->toHaveKey('is_default')
    ->and($array['is_default'])->toBeFalse();
});
