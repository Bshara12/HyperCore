<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Payment\DTOs\RefundDTO;
use App\Models\Payment;
use App\Models\Transaction;

class BuildRefundContextAction
{
  public function execute(Payment $payment, RefundDTO $dto): RefundDTO
  {
    $transaction = $payment->transactions()
      ->where('type', Transaction::TYPE_CHARGE)
      ->where('status', Transaction::STATUS_SUCCESS)
      ->latest()
      ->first();

    throw_if(! $transaction, \Exception::class, 'No successful transaction found.');

    return new RefundDTO(
      paymentId: $payment->id,
      gateway: $payment->gateway,
      amount: $dto->amount,
      currency: $payment->currency,
      reason: $dto->reason,
      transactionId: $transaction->gateway_transaction_id,
      fromWalletId: $transaction->from_wallet_id,
      toWalletId: $transaction->to_wallet_id,
    );
  }
}
