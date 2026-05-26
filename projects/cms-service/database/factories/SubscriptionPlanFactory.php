<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionPlanFactory extends Factory
{
  protected $model = SubscriptionPlan::class;

  public function definition(): array
  {
    $name = $this->faker->unique()->words(2, true);

    return [
      'project_id'    => Project::factory(),
      'name'          => $name,
      'slug'          => Str::slug($name),
      'description'   => $this->faker->sentence(),
      'price'         => $this->faker->randomFloat(2, 0, 100),
      'currency'      => 'USD',
      'duration_days' => $this->faker->randomElement([30, 90, 365]),
      'is_active'     => true,
      'metadata'      => [
        'max_projects' => 5,
        'support'      => 'email',
      ],
    ];
  }

  // إضافة State للخطط المجانية
  public function free(): self
  {
    return $this->state(fn() => [
      'price' => 0.00,
    ]);
  }
}
