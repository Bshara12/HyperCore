<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class ProcessPaymentAction
{
  public function __construct(
    private readonly PaymentRepositoryInterface $repository,
  ) {}

  public function execute(PaymentDTO $dto): array
  {
    return DB::transaction(function () use ($dto) {

      $payment = $this->repository->createPayment($dto);

      try {
        if ($dto->gateway === 'wallet') {
          return $this->processWalletPayment($payment, $dto);
        }

        return $this->processGatewayPayment($payment, $dto);
      } catch (\Throwable $e) {
        $this->repository->updatePaymentStatus($payment, Payment::STATUS_FAILED);
        throw $e;
      }
    });
  }

  // ─── Gateway ──────────────────────────────────────────────────────────────

  private function processGatewayPayment(Payment $payment, PaymentDTO $dto): array
  {
    $gateway = app(PaymentGatewayInterface::class, ['gatewayName' => $dto->gateway]);

    if (! $dto->isInstallment()) {
      // دفع كامل — يستخدم charge() بالمبلغ الكامل
      $result = $gateway->charge($dto);
      return $this->handleGatewayResult($payment, $dto, $result, null);
    }

    // تقسيط — ينشئ الخطة أولاً ثم يدفع الدفعة الأولى
    $this->repository->createInstallmentPlan($payment, $dto);

    $firstAmount = $dto->downPayment ?? $dto->installmentAmount;
    $result      = $gateway->chargeAmount($dto, $firstAmount);

    return $this->handleGatewayResult($payment, $dto, $result, 0); // 0 = down payment
  }

  // ─── Wallet ───────────────────────────────────────────────────────────────

  private function processWalletPayment(Payment $payment, PaymentDTO $dto): array
  {
    $fromWallet = $this->repository->findWalletByUserId($dto->userId);

    throw_if(! $fromWallet, \Exception::class, 'Wallet not found.');

    $amountToCharge = $dto->isInstallment()
      ? ($dto->downPayment ?? $dto->installmentAmount)
      : $dto->amount;

    throw_if(
      ! $fromWallet->hasSufficientBalance($amountToCharge),
      \Exception::class,
      "Insufficient balance. Available: {$fromWallet->balance}, Required: {$amountToCharge}."
    );

    if ($dto->isInstallment()) {
      $this->repository->createInstallmentPlan($payment, $dto);
    }

    $this->repository->debitWallet($fromWallet, $amountToCharge);
    $this->repository->creditWallet($dto->toWallet, $amountToCharge);

    $installmentNumber = $dto->isInstallment() ? 0 : null;

    $transaction = $this->repository->createWalletTransaction(
      payment: $payment,
      type: Transaction::TYPE_CHARGE,
      fromWalletId: $fromWallet->id,
      toWalletId: $dto->toWallet?->id,
      amount: $amountToCharge,
      currency: $dto->currency,
      status: Transaction::STATUS_SUCCESS,
      installmentNumber: $installmentNumber,
    );

    $paymentStatus = $dto->isInstallment()
      ? Payment::STATUS_PENDING
      : Payment::STATUS_PAID;

    $payment = $this->repository->updatePaymentStatus($payment, $paymentStatus);

    return [
      'success'            => true,
      'payment_id'         => $payment->id,
      'transaction_id'     => $transaction->id,
      'payment_method'     => 'wallet',
      'status'             => $payment->status,
      'installment_number' => $installmentNumber,
    ];
  }

  // ─── Helper ───────────────────────────────────────────────────────────────

  private function handleGatewayResult(Payment $payment, PaymentDTO $dto, array $result, ?int $installmentNumber): array
  {
    $status = $result['success']
      ? Transaction::STATUS_SUCCESS
      : Transaction::STATUS_FAILED;

    $this->repository->createGatewayTransaction(
      payment: $payment,
      type: Transaction::TYPE_CHARGE,
      gatewayTransactionId: $result['transaction_id'],
      amount: $result['amount'] ?? $dto->amount,
      currency: $dto->currency,
      status: $status,
      gatewayResponse: $result['raw'],
      installmentNumber: $installmentNumber,
    );

    $paymentStatus = match (true) {
      ! $result['success']       => Payment::STATUS_FAILED,
      is_null($installmentNumber) => Payment::STATUS_PAID,     // دفع كامل
      default                    => Payment::STATUS_PENDING,   // أول قسط
    };

    $payment = $this->repository->updatePaymentStatus($payment, $paymentStatus);

    return [
      'success'            => $result['success'],
      'payment_id'         => $payment->id,
      'transaction_id'     => $result['transaction_id'],
      'payment_method'     => 'gateway',
      'status'             => $payment->status,
      'installment_number' => $installmentNumber,
    ];
  }
}
