<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Transaction;

class TopUpWalletAction
{
  public function __construct(
    private readonly PaymentRepositoryInterface $repository,
  ) {}

  public function execute($dto)
  {
    $this->repository->creditWallet($dto->wallet, $dto->amount);
    $transaction = $this->repository->createWalletTransaction(
      payment: null,
      type: Transaction::TYPE_CHARGE,
      fromWalletId: null,
      toWalletId: $dto->wallet->id,
      amount: $dto->amount,
      currency: 'USD',
      status: Transaction::STATUS_SUCCESS,
      installmentNumber: null
    );

    return [
      'wallet_id'      => $dto->wallet->id,
      'amount_added'   => $dto->amount,
      'new_balance'    => $dto->wallet->fresh()->balance,
      'transaction_id' => $transaction->id,
    ];
  }
}
