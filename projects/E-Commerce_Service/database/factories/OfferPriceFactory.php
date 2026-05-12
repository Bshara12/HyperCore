<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\OfferPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferPriceFactory extends Factory
{
  protected $model = OfferPrice::class;

  public function definition(): array
  {
    $originalPrice = $this->faker->randomFloat(2, 100, 1000);
    $discount = $this->faker->randomFloat(2, 10, 50);
    $finalPrice = $originalPrice - $discount;

    return [
      'entry_id' => $this->faker->numberBetween(1, 10000), // قد يكون ID المنتج أو الـ Cart Item
      'applied_offer_id' => Offer::factory(),
      'original_price' => $originalPrice,
      'final_price' => $finalPrice,
      'is_applied' => true,
      'is_code_price' => false,
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }
}
