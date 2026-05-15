<?php

namespace Tests\Feature\Domains\E_Commerce\Benefits;

use App\Domains\E_Commerce\Benefits\FixedAmountDiscountStrategy;

beforeEach(function () {
  $this->strategy = new FixedAmountDiscountStrategy();
});

it('calculates fixed amount discount correctly', function () {
  $originalPrice = 100.0;
  $quantity = 1;
  $config = ['fixed_amount' => 20.0];

  $result = $this->strategy->calculate($originalPrice, $quantity, $config);

  // 100 - 20 = 80
  expect($result)->toBe(80.0);
});

it('ensures price does not go below zero', function () {
  $originalPrice = 50.0;
  $quantity = 1;
  $config = ['fixed_amount' => 60.0]; // الخصم أكبر من السعر

  $result = $this->strategy->calculate($originalPrice, $quantity, $config);

  // يجب أن يعيد 0 وليس -10
  expect($result)->toBe(0.0);
});

it('handles missing fixed_amount in config', function () {
  $originalPrice = 100.0;
  $quantity = 1;
  $config = []; // مصفوفة فارغة

  $result = $this->strategy->calculate($originalPrice, $quantity, $config);

  // يجب أن يعيد السعر الأصلي (خصم 0)
  expect($result)->toBe(100.0);
});
