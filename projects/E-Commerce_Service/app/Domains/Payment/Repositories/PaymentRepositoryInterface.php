<?php

namespace App\Domains\Payment\Repositories;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Models\Payment;
use App\Models\Transaction;

interface PaymentRepositoryInterface
{
  public function createPayment(PaymentDTO $dto): Payment;
  public function findPayment(int $id): ?Payment;
  public function findPaymentByOrderId(string $orderId): ?Payment;
  public function updatePaymentStatus(Payment $payment, string $status): Payment;
  public function createChargeTransaction(
    Payment $payment,
    string  $gatewayTransactionId,
    string  $status,
    array   $gatewayResponse,
  ): Transaction;
  public function createRefundTransaction(
    Payment   $payment,
    RefundDTO $dto,
    string    $refundId,
    string    $status,
    array     $gatewayResponse,
  ): Transaction;
  public function getTotalRefunded(Payment $payment): float;
}
