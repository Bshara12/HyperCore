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

      $gateway = app(PaymentGatewayInterface::class);
      $payment = $this->repository->findPaymentByOrderId($dto->orderId)
        ?? $this->repository->createPayment($dto);

      try {
        $result = $gateway->charge($dto);

        $status = $result['success']
          ? Transaction::STATUS_SUCCESS
          : Transaction::STATUS_FAILED;

        $this->repository->createChargeTransaction(
          payment: $payment,
          gatewayTransactionId: $result['transaction_id'],
          status: $status,
          gatewayResponse: $result['raw'],
        );

        $paymentStatus = $result['success']
          ? Payment::STATUS_PAID
          : Payment::STATUS_FAILED;

        $payment = $this->repository->updatePaymentStatus($payment, $paymentStatus);

        return [
          'success'        => $result['success'],
          'payment'        => $payment,
          'transaction_id' => $result['transaction_id'],
          'status'         => $paymentStatus,
        ];
      } catch (\Throwable $e) {
        $this->repository->updatePaymentStatus($payment, Payment::STATUS_FAILED);
        // Log::error('Payment processing failed', [
        //   'payment_id' => $payment->id,
        //   'gateway'    => $dto->gateway,
        //   'error'      => $e->getMessage(),
        // ]);
        throw $e;
      }
    });
  }
}
