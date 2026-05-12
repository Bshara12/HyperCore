<?php

namespace Database\Factories;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class WishlistItemFactory extends Factory
{
  protected $model = WishlistItem::class;

  public function definition(): array
  {
    return [
      'wishlist_id' => Wishlist::factory(),
      'product_id' => $this->faker->numberBetween(1, 5000),
      'variant_id' => $this->faker->optional(0.7)->numberBetween(1, 100), // 70% احتمال وجود Variant
      'sort_order' => 0,
      'added_from_cart' => false,
      'product_snapshot' => [
        'name' => $this->faker->word,
        'sku' => $this->faker->unique()->ean8,
        'image' => $this->faker->imageUrl(),
      ],
      'price_when_added' => $this->faker->randomFloat(2, 10, 1000),
      'notify_on_price_drop' => $this->faker->boolean(30), // 30% تفعيل التنبيه
      'notify_on_back_in_stock' => $this->faker->boolean(20),
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }
}
