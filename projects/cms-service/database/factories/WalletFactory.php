<?php

namespace Database\Factories;

use App\Models\Wallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
  protected $model = Wallet::class;

  public function definition(): array
  {
    return [
      'user_id'       => User::factory(),
      'wallet_number' => $this->faker->unique()->numerify('##########'),
      'balance'       => $this->faker->randomFloat(2, 0, 10000),
    ];
  }
}
