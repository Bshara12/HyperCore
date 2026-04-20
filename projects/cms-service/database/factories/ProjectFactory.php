<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
  protected $model = Project::class;

  public function definition()
  {
    return [
      'public_id' => Str::uuid()->toString(),
      'name' => $this->faker->company(),
      'owner_id' => User::factory(),
    ];
  }
}

