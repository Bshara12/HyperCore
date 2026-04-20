<?php

namespace App\Domains\E_Commerce\Benefits;
use InvalidArgumentException;

class BenefitStrategyFactory
{
  public function make(string $benefitType): BenefitStrategy
  {
    return match (strtolower($benefitType)) {
      'percentage' => new PercentageDiscountStrategy(),
      'fixed_amount' => new FixedAmountDiscountStrategy(),
      default => throw new InvalidArgumentException("Unsupported benefit_type: {$benefitType}"),
    };
  }
}

