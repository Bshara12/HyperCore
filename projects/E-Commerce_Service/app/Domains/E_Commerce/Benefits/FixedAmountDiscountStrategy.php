<?php

namespace App\Domains\E_Commerce\Benefits;

class FixedAmountDiscountStrategy implements BenefitStrategy
{
  public function calculate(float $originalPrice, int $quantity, array $config): float
  {
    $amount = (float)($config['fixed_amount'] ?? 0);
    $amount = max(0.0, $amount);

    return max(0.0, $originalPrice - $amount);
  }
}

