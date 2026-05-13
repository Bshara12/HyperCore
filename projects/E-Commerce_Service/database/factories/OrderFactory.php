<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
  protected $model = Order::class;

  public function definition(): array
  {
    return [
      'user_id' => $this->faker->numberBetween(1, 1000),
      'project_id' => $this->faker->numberBetween(1, 100),
      'status' => 'pending',
      'total_price' => $this->faker->randomFloat(2, 50, 2000), // سعر بين 50 و 2000
      'currency' => 'USD',
      'address' => [
        'street' => $this->faker->streetAddress,
        'city' => $this->faker->city,
        'country' => $this->faker->country,
        'zip' => $this->faker->postcode,
      ],
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }

  /**
   * حالة خاصة للطلبات المكتملة
   */
  public function completed(): self
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'completed',
    ]);
  }
}
