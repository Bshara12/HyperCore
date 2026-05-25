<?php

namespace Database\Factories;

use App\Models\InstallmentPlan;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstallmentPlanFactory extends Factory
{
  protected $model = InstallmentPlan::class;

  public function definition(): array
  {
    return [
      'payment_id'         => Payment::factory(),
      'down_payment'       => 100.0,
      'installment_amount' => 200.0,
      'total_installments' => 5,
      'paid_installments'  => 1,
      'interval_days'      => 30,
      'next_due_date'      => now()->addDays(30),
      'status'             => InstallmentPlan::STATUS_ACTIVE,
    ];
  }
}
