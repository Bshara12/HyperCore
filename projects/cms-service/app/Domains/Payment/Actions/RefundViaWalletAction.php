<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Payment\DTOs\RefundDTO;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\Transaction;

class RefundViaWalletAction
{
  public function __construct(
    private PaymentRepositoryInterface $repository
  ) {}

  public function execute(Payment $payment, RefundDTO $dto): array
  {
    $wallet = $this->repository->findWalletByUserId($payment->user_id);

    throw_if(! $wallet, \Exception::class, 'Wallet not found.');

    $this->repository->creditWallet($wallet, $dto->amount);

    $transaction = $this->repository->createWalletTransaction(
      payment: $payment,
      type: Transaction::TYPE_REFUND,
      fromWalletId: $dto->fromWalletId,
      toWalletId: $wallet->id,
      amount: $dto->amount,
      currency: $dto->currency,
      status: Transaction::STATUS_SUCCESS,
      installmentNumber: null,
    );

    $this->updatePaymentStatus($payment);

    return [
      'success'        => true,
      'payment_id'     => $payment->id,
      'refund_id'      => $transaction->id,
      'payment_method' => 'wallet',
      'status'         => $payment->fresh()->status,
    ];
  }

  private function updatePaymentStatus(Payment $payment): void
  {
    $refunded = $payment->transactions()
      ->where('type', Transaction::TYPE_REFUND)
      ->where('status', Transaction::STATUS_SUCCESS)
      ->sum('amount');

    if ($refunded >= $payment->amount) {
      $this->repository->updatePaymentStatus($payment, Payment::STATUS_REFUNDED);
    }
  }
}