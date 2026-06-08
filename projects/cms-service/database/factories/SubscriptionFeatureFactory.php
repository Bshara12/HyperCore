<?php

namespace Database\Factories;

use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFeatureFactory extends Factory
{
  protected $model = SubscriptionFeature::class;

  public function definition(): array
  {
    $type = $this->faker->randomElement(['boolean', 'limit']);

    return [
      'plan_id'      => SubscriptionPlan::factory(),
      'feature_key'  => $this->faker->unique()->slug(),
      'feature_type' => $type,
      'feature_value' => $type === 'boolean' ? ['enabled' => true] : ['limit' => 10],
    ];
  }
}
