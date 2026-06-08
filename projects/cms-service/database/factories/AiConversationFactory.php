<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiConversationFactory extends Factory
{
  protected $model = AiConversation::class;

  public function definition(): array
  {
    return [
      'user_id' => User::factory(), // يقوم بإنشاء مستخدم تلقائياً إذا لم يتم توفيره
      'title' => $this->faker->sentence(),
      'provisioned_project_id' => null,
      'status' => $this->faker->randomElement(['active', 'archived']),
      'created_at' => now(),
      'updated_at' => now(),
    ];
  }
}
