<?php

namespace Database\Factories;

use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WishlistFactory extends Factory
{
  protected $model = Wishlist::class;

  public function definition(): array
  {
    return [
      'user_id' => $this->faker->numberBetween(1, 1000),
      'guest_token' => null,
      'name' => $this->faker->words(2, true),
      'is_default' => false,
      'visibility' => 'private',
      'share_token' => null,
      'is_shareable' => false,
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }

  /**
   * حالة القائمة العامة القابلة للمشاركة
   */
  public function public(): self
  {
    return $this->state(fn(array $attributes) => [
      'visibility' => 'public',
      'is_shareable' => true,
      'share_token' => Str::random(32),
    ]);
  }

  /**
   * حالة القائمة الخاصة بالضيوف
   */
  public function guest(): self
  {
    return $this->state(fn(array $attributes) => [
      'user_id' => null,
      'guest_token' => Str::uuid()->toString(),
    ]);
  }
}
