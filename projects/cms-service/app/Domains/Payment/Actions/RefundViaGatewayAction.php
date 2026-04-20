<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Core\Actions\Action;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\Transaction;

class RefundViaGatewayAction extends Action
{
  public function __construct(
    private PaymentRepositoryInterface $repository
  ) {}
  protected function circuitServiceName(): string
  {
    return 'Payment-service';
  }
  public function execute(Payment $payment, RefundDTO $dto): array
  {
    $gateway = app(PaymentGatewayInterface::class, ['gatewayName' => $dto->gateway]);

    $result = $gateway->refund($dto);

    $status = $result['success']
      ? Transaction::STATUS_SUCCESS
      : Transaction::STATUS_FAILED;

    $this->repository->createGatewayTransaction(
      payment: $payment,
      type: Transaction::TYPE_REFUND,
      gatewayTransactionId: $result['refund_id'],
      amount: $dto->amount,
      currency: $dto->currency,
      status: $status,
      gatewayResponse: $result['raw'],
      installmentNumber: null,
    );

    if ($result['success']) {
      $this->updatePaymentStatus($payment);
    }

    return [
      'success'        => $result['success'],
      'payment_id'     => $payment->id,
      'refund_id'      => $result['refund_id'],
      'payment_method' => 'gateway',
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
