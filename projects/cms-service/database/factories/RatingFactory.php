<?php

namespace Database\Factories;

use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingFactory extends Factory
{
  protected $model = Rating::class;

  public function definition(): array
  {
    return [
      'user_id' => User::factory(),
      'rating'  => $this->faker->numberBetween(1, 5),
      'review'  => $this->faker->sentence(),
      // نترك الـ rateable فارغاً ليتم تحديده عند الاستدعاء
    ];
  }
}
