<?php

namespace App\Domains\E_Commerce\Benefits;

class PercentageDiscountStrategy implements BenefitStrategy
{
    public function calculate(float $originalPrice, int $quantity, array $config): float
    {
        $pct = (float) ($config['percentage'] ?? 0);
        $pct = max(0.0, min(100.0, $pct));

        $discount = $originalPrice * ($pct / 100.0);

        return max(0.0, $originalPrice - $discount);
    }
}
