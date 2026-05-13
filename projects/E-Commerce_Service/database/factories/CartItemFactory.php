<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartItemFactory extends Factory
{
  protected $model = CartItem::class;

  public function definition(): array
  {
    return [
      // سيقوم لارافيل بإنشاء Cart جديد وربط الـ ID الخاص به هنا
      'cart_id' => Cart::factory(),
      'item_id' => $this->faker->numberBetween(1, 5000),
      'quantity' => $this->faker->numberBetween(1, 10),
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }
}
