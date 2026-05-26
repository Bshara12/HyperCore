<?php

namespace Database\Factories;

use App\Models\DataType;
use App\Models\DataTypeRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataTypeRelationFactory extends Factory
{
  protected $model = DataTypeRelation::class;

  public function definition(): array
  {
    return [
      'data_type_id'         => DataType::factory(),
      'related_data_type_id' => DataType::factory(),
      'relation_type'        => $this->faker->randomElement(['one-to-one', 'one-to-many', 'many-to-many']),
      'relation_name'        => $this->faker->word(),
      'pivot_table'          => $this->faker->word(),
    ];
  }
}
