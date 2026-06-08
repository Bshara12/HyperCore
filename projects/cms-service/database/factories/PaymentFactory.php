<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
  protected $model = Payment::class;

  public function definition(): array
  {
    return [
      'user_id' => User::factory(),
      'project_id' => Project::factory(),
      'gateway' => 'stripe',
      'payment_type' => Payment::TYPE_FULL,
      'amount' => 100.00,
      'currency' => 'USD',
      'status' => Payment::STATUS_PENDING,
    ];
  }
}
