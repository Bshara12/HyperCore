<?php

namespace Tests\Feature\Domains\E_Commerce\Benefits;

// ملاحظة: سنقوم بعمل كلاسات وهمية (Anonymous Classes) داخل الاختبار 
// لمحاكاة تطبيق الـ Interface واختبار المنطق.

use App\Domains\E_Commerce\Benefits\BenefitStrategy;

it('calculates percentage discount correctly', function () {
  // 1. تعريف استراتيجية الخصم بالنسبة المئوية
  $percentageStrategy = new class implements BenefitStrategy {
    public function calculate(float $originalPrice, int $quantity, array $config): float
    {
      $discount = $config['percentage'] / 100;
      return ($originalPrice * $quantity) * (1 - $discount);
    }
  };

  $originalPrice = 100.0;
  $quantity = 2;
  $config = ['percentage' => 10]; // خصم 10%

  $finalPrice = $percentageStrategy->calculate($originalPrice, $quantity, $config);

  // الحساب المتوقع: (100 * 2) - 10% = 180
  expect($finalPrice)->toBe(180.0);
});

it('calculates fixed amount discount correctly', function () {
  // 2. تعريف استراتيجية الخصم بمبلغ ثابت
  $fixedStrategy = new class implements BenefitStrategy {
    public function calculate(float $originalPrice, int $quantity, array $config): float
    {
      return ($originalPrice * $quantity) - $config['amount'];
    }
  };

  $originalPrice = 50.0;
  $quantity = 3;
  $config = ['amount' => 20]; // خصم 20 ريال/دولار من الإجمالي

  $finalPrice = $fixedStrategy->calculate($originalPrice, $quantity, $config);

  // الحساب المتوقع: (50 * 3) - 20 = 130
  expect($finalPrice)->toBe(130.0);
});
