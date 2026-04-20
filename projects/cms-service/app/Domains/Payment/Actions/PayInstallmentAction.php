<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PayInstallmentAction
{
  public function __construct(
    private readonly PaymentRepositoryInterface $repository,
  ) {}

  public function execute(PayInstallmentDTO $dto): array
  {
    return DB::transaction(function () use ($dto) {

      $payment = $this->repository->findPayment($dto->paymentId);

      throw_if(! $payment,               \Exception::class, 'Payment not found.');
      throw_if(! $payment->isInstallment(), \Exception::class, 'This payment is not an installment plan.');

      $plan = $payment->installmentPlan;

      throw_if(! $plan,                          \Exception::class, 'Installment plan not found.');
      throw_if($plan->isCompleted(),              \Exception::class, 'All installments have been paid.');
      throw_if($plan->status === 'defaulted',     \Exception::class, 'This installment plan is defaulted.');

      $installmentNumber = $plan->nextInstallmentNumber();
      $amount            = $plan->installment_amount;

      // ─── دفع عبر المحفظة ──────────────────────────────────────────
      if ($dto->gateway === 'wallet') {
        return $this->payViaWallet($payment, $plan, $dto, $amount, $installmentNumber);
      }

      // ─── دفع عبر Gateway ──────────────────────────────────────────
      return $this->payViaGateway($payment, $plan, $dto, $amount, $installmentNumber);
    });
  }

  // ─── Gateway ──────────────────────────────────────────────────────────────

  private function payViaGateway($payment, $plan, PayInstallmentDTO $dto, float $amount, int $installmentNumber): array
  {
    $gateway = app(PaymentGatewayInterface::class, ['gatewayName' => $dto->gateway]);

    $chargeDto = new PaymentDTO(
      userId: $payment->user_id,
      userName: '',
      projectId: $payment->project_id,
      amount: $amount,
      currency: $dto->currency,
      gateway: $dto->gateway,
      paymentType: 'full'
    );

    $result = $gateway->charge($chargeDto);

    $status = $result['success']
      ? Transaction::STATUS_SUCCESS
      : Transaction::STATUS_FAILED;

    $this->repository->createGatewayTransaction(
      payment: $payment,
      type: Transaction::TYPE_CHARGE,
      gatewayTransactionId: $result['transaction_id'],
      amount: $amount,
      currency: $dto->currency,
      status: $status,
      gatewayResponse: $result['raw'],
      installmentNumber: $installmentNumber,
    );

    if ($result['success']) {
      $plan = $this->repository->incrementPaidInstallments($plan);

      if ($plan->isCompleted()) {
        $this->repository->markPlanCompleted($plan);
        $this->repository->updatePaymentStatus($payment, Payment::STATUS_PAID);
      }
    }

    return [
      'success'            => $result['success'],
      'payment_id'         => $payment->id,
      'transaction_id'     => $result['transaction_id'],
      'payment_method'     => 'gateway',
      'installment_number' => $installmentNumber,
      'remaining'          => $plan->remainingInstallments(),
      'plan_status'        => $plan->status,
    ];
  }

  // ─── Wallet ───────────────────────────────────────────────────────────────

  private function payViaWallet($payment, $plan, PayInstallmentDTO $dto, float $amount, int $installmentNumber): array
  {
    $fromWallet = $this->repository->findWalletByUserId($payment->user_id);

    throw_if(! $fromWallet, \Exception::class, 'Wallet not found.');
    throw_if(
      ! $fromWallet->hasSufficientBalance($amount),
      \Exception::class,
      "Insufficient wallet balance. Available: {$fromWallet->balance}, Required: {$amount}."
    );

    $this->repository->debitWallet($fromWallet, $amount);
    $this->repository->creditWallet($dto->toWallet, $amount);

    $transaction = $this->repository->createWalletTransaction(
      payment: $payment,
      type: Transaction::TYPE_CHARGE,
      fromWalletId: $fromWallet->id,
      toWalletId: $dto->toWallet->id,
      amount: $amount,
      currency: $dto->currency,
      status: Transaction::STATUS_SUCCESS,
      installmentNumber: $installmentNumber,
    );

    $plan = $this->repository->incrementPaidInstallments($plan);

    if ($plan->isCompleted()) {
      $this->repository->markPlanCompleted($plan);
      $this->repository->updatePaymentStatus($payment, Payment::STATUS_PAID);
    }

    return [
      'success'            => true,
      'payment_id'         => $payment->id,
      'transaction_id'     => $transaction->id,
      'payment_method'     => 'wallet',
      'installment_number' => $installmentNumber,
      'remaining'          => $plan->remainingInstallments(),
      'plan_status'        => $plan->status,
    ];
  }
}
