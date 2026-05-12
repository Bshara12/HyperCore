<?php

namespace Database\Factories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OfferFactory extends Factory
{
  protected $model = Offer::class;

  public function definition(): array
  {
    return [
      'project_id' => $this->faker->numberBetween(1, 100),
      'collection_id' => $this->faker->numberBetween(1, 500),
      'is_code_offer' => false,
      'offer_duration' => null,
      'code' => null,
      'benefit_type' => $this->faker->randomElement(['percentage', 'fixed_amount', 'free_shipping']),
      'benefit_config' => [
        'value' => $this->faker->randomFloat(2, 5, 50),
        'min_purchase' => $this->faker->numberBetween(100, 500)
      ],
      'start_at' => now()->subDays(1),
      'end_at' => now()->addDays(30),
      'is_active' => true,
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }

  /**
   * عرض يعتمد على كود (كوبون)
   */
  public function code(string $customCode = null): self
  {
    return $this->state(fn(array $attributes) => [
      'is_code_offer' => true,
      'code' => $customCode ?? strtoupper(Str::random(8)),
      'offer_duration' => 3600, // مثلاً ساعة واحدة من تاريخ الاستخدام
    ]);
  }

  /**
   * عرض منتهي الصلاحية
   */
  public function expired(): self
  {
    return $this->state(fn(array $attributes) => [
      'start_at' => now()->subDays(10),
      'end_at' => now()->subDay(),
    ]);
  }
}
