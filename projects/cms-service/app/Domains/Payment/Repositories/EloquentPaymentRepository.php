<?php

namespace App\Domains\Payment\Repositories;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Wallet;

class EloquentPaymentRepository implements PaymentRepositoryInterface
{
  // ─── Payment ──────────────────────────────────────────────────────────────

  public function createPayment(PaymentDTO $dto): Payment
  {
    return Payment::create([
      'user_id'      => $dto->userId,
      'project_id'   => $dto->projectId,
      'gateway'      => $dto->gateway,
      'payment_type' => $dto->paymentType,
      'amount'       => $dto->amount,
      'currency'     => $dto->currency,
      'status'       => Payment::STATUS_PENDING,
      'description'  => $dto->description,
    ]);
  }

  public function findPayment(int $id): ?Payment
  {
    return Payment::with(['installmentPlan', 'transactions'])->find($id);
  }

  public function updatePaymentStatus(Payment $payment, string $status): Payment
  {
    $payment->update(['status' => $status]);
    return $payment->fresh();
  }

  // ─── Installment Plan ─────────────────────────────────────────────────────

  public function createInstallmentPlan(Payment $payment, PaymentDTO $dto): InstallmentPlan
  {
    return InstallmentPlan::create([
      'payment_id'         => $payment->id,
      'down_payment'       => $dto->downPayment ?? 0,
      'installment_amount' => $dto->installmentAmount,
      'total_installments' => $dto->totalInstallments,
      'paid_installments'  => 0,
      'interval_days'      => $dto->intervalDays ?? 30,
      'next_due_date'      => now()->addDays($dto->intervalDays ?? 30),
      'status'             => InstallmentPlan::STATUS_ACTIVE,
    ]);
  }

  public function incrementPaidInstallments(InstallmentPlan $plan): InstallmentPlan
  {
    $plan->increment('paid_installments');
    $plan->update([
      'next_due_date' => now()->addDays($plan->interval_days),
    ]);
    return $plan->fresh();
  }

  public function markPlanCompleted(InstallmentPlan $plan): InstallmentPlan
  {
    $plan->update(['status' => InstallmentPlan::STATUS_COMPLETED, 'next_due_date' => null]);
    return $plan->fresh();
  }

  // ─── Transaction (Gateway) ────────────────────────────────────────────────

  public function createGatewayTransaction(
    Payment $payment,
    string  $type,
    string  $gatewayTransactionId,
    float   $amount,
    string  $currency,
    string  $status,
    array   $gatewayResponse,
    ?int    $installmentNumber,
  ): Transaction {
    return Transaction::create([
      'payment_id'             => $payment->id,
      'type'                   => $type,
      'payment_method'         => Transaction::METHOD_GATEWAY,
      'gateway_transaction_id' => $gatewayTransactionId,
      'gateway_response'       => $gatewayResponse,
      'from_wallet_id'         => null,
      'to_wallet_id'           => null,
      'installment_number'     => $installmentNumber,
      'amount'                 => $amount,
      'currency'               => $currency,
      'status'                 => $status,
      'processed_at'           => now(),
    ]);
  }

  // ─── Transaction (Wallet) ─────────────────────────────────────────────────

  public function createWalletTransaction(
    ?Payment $payment,
    string  $type,
    ?int     $fromWalletId,
    int     $toWalletId,
    float   $amount,
    string  $currency,
    string  $status,
    ?int    $installmentNumber,
  ): Transaction {
    return Transaction::create([
      'payment_id'             => $payment?->id,
      'type'                   => $type,
      'payment_method'         => Transaction::METHOD_WALLET,
      'gateway_transaction_id' => null,
      'gateway_response'       => null,
      'from_wallet_id'         => $fromWalletId,
      'to_wallet_id'           => $toWalletId,
      'installment_number'     => $installmentNumber,
      'amount'                 => $amount,
      'currency'               => $currency,
      'status'                 => $status,
      'processed_at'           => now(),
    ]);
  }

  // ─── Wallet ───────────────────────────────────────────────────────────────

  public function findWalletByUserId(int $userId): ?Wallet
  {
    return Wallet::where('user_id', $userId)->first();
  }

  public function debitWallet(Wallet $wallet, float $amount): void
  {
    $wallet->decrement('balance', $amount);
  }

  public function creditWallet(Wallet $wallet, float $amount): void
  {
    $wallet->increment('balance', $amount);
  }
}
