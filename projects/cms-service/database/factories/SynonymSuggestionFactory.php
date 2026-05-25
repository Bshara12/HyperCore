<?php

namespace Database\Factories;

use App\Models\SynonymSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

class SynonymSuggestionFactory extends Factory
{
  protected $model = SynonymSuggestion::class;

  public function definition(): array
  {
    return [
      'project_id'         => 1,
      'word_a'             => $this->faker->word(),
      'word_b'             => $this->faker->word(),
      'language'           => 'en',
      'jaccard_score'      => $this->faker->randomFloat(6, 0, 1),
      'cooccurrence_count' => $this->faker->numberBetween(1, 1000),
      'confidence_score'   => $this->faker->randomFloat(4, 0, 1),
      'word_a_count'       => $this->faker->numberBetween(10, 5000),
      'word_b_count'       => $this->faker->numberBetween(10, 5000),
      'status'             => 'pending',
      'last_computed_at'   => now(),
    ];
  }

  public function approved(): self
  {
    return $this->state(fn() => [
      'status' => 'approved',
      'reviewed_at' => now(),
    ]);
  }
}
