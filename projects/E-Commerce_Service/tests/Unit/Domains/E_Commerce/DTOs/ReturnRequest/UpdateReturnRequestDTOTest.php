<?php

use App\Domains\E_Commerce\DTOs\ReturnRequest\UpdateReturnRequestDTO;

it('can be instantiated and stores values correctly', function () {
  // 1. Arrange & Act
  $id = 500;
  $status = 'approved';
  $dto = new UpdateReturnRequestDTO($id, $status);

  // 2. Assert
  expect($dto->id)->toBe(500)
    ->and($dto->status)->toBe('approved');
});

it('can handle different status strings', function () {
  // اختبار مرونة النوع النصي للحالة
  $status = 'rejected';
  $dto = new UpdateReturnRequestDTO(1, $status);

  expect($dto->status)->toBe('rejected');
});
