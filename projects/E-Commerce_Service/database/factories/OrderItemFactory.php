<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
  protected $model = OrderItem::class;

  public function definition(): array
  {
    $price = $this->faker->randomFloat(2, 10, 500);
    $quantity = $this->faker->numberBetween(1, 5);

    return [
      'order_id' => Order::factory(), // ربط تلقائي بطلب جديد
      'product_id' => $this->faker->numberBetween(1, 5000),
      'status' => 'pending',
      'price' => $price,
      'quantity' => $quantity,
      'total' => $price * $quantity, // حساب الإجمالي تلقائياً
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }
}
