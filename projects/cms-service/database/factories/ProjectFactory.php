<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'public_id' => Str::uuid(), // يولد UUID فريد (36 حرف)
      'slug'      => $this->faker->unique()->slug(),
      'name'      => $this->faker->company(),
      'owner_id'  => $this->faker->numberBetween(1, 100),
  
      // بيانات JSON
      'supported_languages' => ['en', 'ar'],
      'enabled_modules'     => ['analytics', 'messaging'],

      // التقييمات الافتراضية
      'ratings_count' => 0,
      'ratings_avg'   => 0.00,
    ];
  }
}
