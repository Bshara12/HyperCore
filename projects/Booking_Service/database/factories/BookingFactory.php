<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
  protected $model = Booking::class;

  public function definition(): array
  {
    $startAt = now()->addDays(rand(1, 30))->setHour(rand(8, 16))->setMinute(0);
    $endAt = (clone $startAt)->addHours(rand(1, 3));

    return [
      'resource_id' => Resource::factory(),
      'user_id' => fake()->randomNumber(5),
      'project_id' => fake()->randomNumber(5),
      'payment_id' => null,
      'start_at' => $startAt,
      'end_at' => $endAt,
      'status' => fake()->randomElement(['pending', 'confirmed', 'cancelled', 'completed', 'no_show']),
      'amount' => fake()->randomFloat(2, 10, 500),
      'currency' => 'USD',
      'notes' => fake()->sentence(),
      'cancellation_reason' => null,
      'refund_amount' => null,
    ];
  }
}
