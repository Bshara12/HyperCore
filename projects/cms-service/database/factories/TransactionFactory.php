<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
  protected $model = Transaction::class;

  public function definition(): array
  {
    return [
      'payment_id'             => Payment::factory(),
      'type'                   => $this->faker->randomElement(['charge', 'refund']),
      'payment_method'         => 'gateway',
      'gateway_transaction_id' => $this->faker->uuid(),
      'gateway_response'       => json_encode(['status' => 'success']),
      'from_wallet_id'         => null,
      'to_wallet_id'           => null,
      'installment_number'     => null,
      'amount'                 => $this->faker->randomFloat(2, 10, 1000),
      'currency'               => 'USD',
      'status'                 => 'success',
      'processed_at'           => now(),
    ];
  }

  // إضافات مفيدة للاختبارات (States)
  public function walletPayment(): Factory
  {
    return $this->state(fn(array $attributes) => [
      'payment_method' => 'wallet',
      'from_wallet_id' => Wallet::factory(),
      'to_wallet_id'   => Wallet::factory(),
      'gateway_transaction_id' => null,
    ]);
  }
}
