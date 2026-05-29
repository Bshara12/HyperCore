<?php

namespace Database\Factories;

use App\Models\AiMessage;
use App\Models\AiConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiMessageFactory extends Factory
{
  protected $model = AiMessage::class;

  public function definition(): array
  {
    return [
      'conversation_id' => AiConversation::factory(), // يربط الرسالة بمحادثة جديدة تلقائياً
      'role' => $this->faker->randomElement(['user', 'assistant']),
      'content' => $this->faker->paragraph(),
      'schema' => null,
      'is_provisioned' => false,
      'sequence' => $this->faker->numberBetween(1, 10),
      'created_at' => now(),
    ];
  }
}
