<?php

namespace App\Domains\Payment\Actions;

use App\Domains\Payment\DTOs\RefundDTO;
use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundPaymentAction
{
  public function __construct(
    private readonly PaymentRepositoryInterface $repository,
  ) {}

  public function execute(RefundDTO $dto): array
  {
    return DB::transaction(function () use ($dto) {

      $gateway = app(PaymentGatewayInterface::class);
      $payment = $this->repository->findPayment((int) $dto->paymentId);

      throw_if(! $payment, \Exception::class, 'Payment not found.');
      throw_if(! $payment->isPaid(), \Exception::class, 'Payment is not in a paid state.');

      $totalRefunded = $this->repository->getTotalRefunded($payment);
      $remaining     = $payment->amount - $totalRefunded;

      throw_if(
        $dto->amount > $remaining,
        \Exception::class,
        "Refund amount ({$dto->amount}) exceeds remaining refundable amount ({$remaining})."
      );

      try {
        $result = $gateway->refund($dto);

        $status = $result['success']
          ? Transaction::STATUS_SUCCESS
          : Transaction::STATUS_FAILED;

        $this->repository->createRefundTransaction(
          payment: $payment,
          dto: $dto,
          refundId: $result['refund_id'],
          status: $status,
          gatewayResponse: $result['raw'],
        );

        if ($result['success']) {
          $paymentStatus = Payment::STATUS_REFUNDED;

          $payment = $this->repository->updatePaymentStatus($payment, $paymentStatus);
        }

        return [
          'success' => $result['success'],
          'payment' => $payment,
          'refund_id' => $result['refund_id'],
          'status'  => $payment->status,
        ];
      } catch (\Throwable $e) {
        Log::error('Refund processing failed', [
          'payment_id' => $payment->id,
          'gateway'    => $dto->gateway,
          'error'      => $e->getMessage(),
        ]);

        throw $e;
      }
    });
  }
}
