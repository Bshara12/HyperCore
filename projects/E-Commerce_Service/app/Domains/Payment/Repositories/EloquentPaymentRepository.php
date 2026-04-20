<?php

namespace App\Domains\Payment\Repositories;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Models\Payment;
use App\Models\Transaction;

class EloquentPaymentRepository implements PaymentRepositoryInterface
{
  // ─── Payment ──────────────────────────────────────────────────────────────

  public function createPayment(PaymentDTO $dto): Payment
  {
    return Payment::create([
      'order_id'       => $dto->orderId,
      'user_id'  => $dto->userId,
      'project_id'  => $dto->projectId,
      'gateway'        => $dto->gateway,
      'amount'         => $dto->amount,
      'currency'       => $dto->currency,
      'status'         => Payment::STATUS_PENDING,
      'description'    => $dto->description,
    ]);
  }

  public function findPayment(int $id): ?Payment
  {
    return Payment::find($id);
  }

  public function findPaymentByOrderId(string $orderId): ?Payment
  {
    return Payment::where('order_id', $orderId)->latest()->first();
  }

  public function updatePaymentStatus(Payment $payment, string $status): Payment
  {
    $payment->update(['status' => $status]);
    return $payment->fresh();
  }

  // ─── Transaction ──────────────────────────────────────────────────────────

  public function createChargeTransaction(
    Payment $payment,
    string  $gatewayTransactionId,
    string  $status,
    array   $gatewayResponse,
  ): Transaction {
    return Transaction::create([
      'payment_id'             => $payment->id,
      'gateway_transaction_id' => $gatewayTransactionId,
      'type'                   => Transaction::TYPE_CHARGE,
      'amount'                 => $payment->amount,
      'currency'               => $payment->currency,
      'status'                 => $status,
      'gateway_response'       => $gatewayResponse,
      'processed_at'           => now(),
    ]);
  }

  public function createRefundTransaction(
    Payment   $payment,
    RefundDTO $dto,
    string    $refundId,
    string    $status,
    array     $gatewayResponse,
  ): Transaction {
    return Transaction::create([
      'payment_id'             => $payment->id,
      'gateway_transaction_id' => $refundId,
      'type'                   => Transaction::TYPE_REFUND,
      'amount'                 => $dto->amount,
      'currency'               => $dto->currency,
      'status'                 => $status,
      'gateway_response'       => $gatewayResponse,
      'processed_at'           => now(),
    ]);
  }

  public function getTotalRefunded(Payment $payment): float
  {
    return (float) $payment->transactions()
      ->where('type', Transaction::TYPE_REFUND)
      ->where('status', Transaction::STATUS_SUCCESS)
      ->sum('amount');
  }
}
