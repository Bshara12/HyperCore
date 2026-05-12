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
      'project_id' => $this->faker->numberBetween(1, 100),
      'user_id' => $this->faker->numberBetween(1, 1000),
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }
}
