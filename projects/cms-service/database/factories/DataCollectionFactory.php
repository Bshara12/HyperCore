<?php

namespace Database\Factories;

use App\Models\DataCollection;
use App\Models\Project;
use App\Models\DataType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataCollectionFactory extends Factory
{
  protected $model = DataCollection::class;

  public function definition()
  {
    return [
      'project_id' => Project::factory(),
      'data_type_id' => DataType::factory(),
      'name' => $this->faker->words(3, true),
      'slug' => $this->faker->unique()->slug(),
      'type' => 'manual',
      'is_active' => true,
    ];
  }
}
