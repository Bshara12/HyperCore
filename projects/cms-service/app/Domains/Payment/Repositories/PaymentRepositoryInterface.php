<?php

namespace App\Domains\Payment\Repositories;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Wallet;

interface PaymentRepositoryInterface
{
  // ─── Payment ──────────────────────────────────────────────────────────────
  public function createPayment(PaymentDTO $dto): Payment;
  public function findPayment(int $id): ?Payment;
  public function updatePaymentStatus(Payment $payment, string $status): Payment;

  // ─── Installment Plan ─────────────────────────────────────────────────────
  public function createInstallmentPlan(Payment $payment, PaymentDTO $dto): InstallmentPlan;
  public function incrementPaidInstallments(InstallmentPlan $plan): InstallmentPlan;
  public function markPlanCompleted(InstallmentPlan $plan): InstallmentPlan;

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
  ): Transaction;

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
  ): Transaction;

  // ─── Wallet ───────────────────────────────────────────────────────────────
  public function findWalletByUserId(int $userId): ?Wallet;
  public function debitWallet(Wallet $wallet, float $amount): void;
  public function creditWallet(Wallet $wallet, float $amount): void;
}
