<?php

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
  protected $model = Cart::class;

  public function definition(): array
  {
    return [
      /*
       | Out of the range tests use.
       |
       | CartItemFactory creates a Cart for every item, so a default inside
       | 1..100 could silently add a cart to the project a test is counting —
       | the same rare, CI-only failure ResourceFactory caused in Booking.
       */
      'project_id' => $this->faker->numberBetween(100000, 999999),
      'user_id' => $this->faker->numberBetween(1, 1000),
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }
}
