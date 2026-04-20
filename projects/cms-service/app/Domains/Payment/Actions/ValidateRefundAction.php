<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Core\Actions\Action;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Models\Payment;
use App\Models\Transaction;

class ValidateRefundAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'Payment-service';
  }
  public function execute(Payment $payment, RefundDTO $dto): void
  {
    $totalRefunded = $payment->transactions()
      ->where('type', Transaction::TYPE_REFUND)
      ->where('status', Transaction::STATUS_SUCCESS)
      ->sum('amount');

    $remaining = $payment->amount - $totalRefunded;

    throw_if($dto->amount > $remaining, \Exception::class, 'Refund exceeds remaining amount.');
  }
}
