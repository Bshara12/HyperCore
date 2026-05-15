<?php

namespace Tests\Feature\Domains\E_Commerce\Benefits;

use App\Domains\E_Commerce\Benefits\PercentageDiscountStrategy;

beforeEach(function () {
  $this->strategy = new PercentageDiscountStrategy();
});

it('calculates percentage discount correctly', function () {
  $originalPrice = 200.0;
  $quantity = 1;
  $config = ['percentage' => 15]; // خصم 15%

  $result = $this->strategy->calculate($originalPrice, $quantity, $config);

  // 200 * 0.15 = 30 -> 200 - 30 = 170
  expect($result)->toBe(170.0);
});

it('caps discount at 100 percent', function () {
  $originalPrice = 100.0;
  $quantity = 1;
  $config = ['percentage' => 150]; // نسبة خاطئة (أكبر من 100)

  $result = $this->strategy->calculate($originalPrice, $quantity, $config);

  // بفضل min(100.0) يجب أن يعيد 0 وليس -50
  expect($result)->toBe(0.0);
});

it('handles zero or negative percentage', function () {
  $originalPrice = 100.0;
  $quantity = 1;
  $config = ['percentage' => -10];

  $result = $this->strategy->calculate($originalPrice, $quantity, $config);

  // بفضل max(0.0) يجب أن يعيد السعر الأصلي 100
  expect($result)->toBe(100.0);
});

it('returns original price if percentage is missing', function () {
  $originalPrice = 50.0;
  $quantity = 1;
  $config = [];

  $result = $this->strategy->calculate($originalPrice, $quantity, $config);

  expect($result)->toBe(50.0);
});
