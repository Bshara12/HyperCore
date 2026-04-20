<?php

namespace App\Domains\Payment\Services;

use App\Domains\Payment\Actions\BuildRefundContextAction;
use App\Domains\Payment\Actions\FindPaymentAction;
use App\Domains\Payment\Actions\PayInstallmentAction;
use App\Domains\Payment\Actions\ProcessPaymentAction;
use App\Domains\Payment\Actions\RefundPaymentAction;
use App\Domains\Payment\Actions\RefundViaGatewayAction;
use App\Domains\Payment\Actions\RefundViaWalletAction;
use App\Domains\Payment\Actions\TopUpWalletAction;
use App\Domains\Payment\Actions\ValidateRefundAction;
use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Domains\Payment\DTOs\TopUpDTO;
use Illuminate\Support\Facades\DB;

class PaymentService
{
  public function __construct(
    private readonly ProcessPaymentAction  $processAction,
    private readonly PayInstallmentAction  $installmentAction,
    private readonly TopUpWalletAction   $topUpActon,
    private readonly FindPaymentAction $findPayment,
    private readonly ValidateRefundAction $validateRefund,
    private readonly BuildRefundContextAction $buildContext,
    private readonly RefundViaGatewayAction $refundViaGateway,
    private readonly RefundViaWalletAction $refundViaWallet,
  ) {}

  public function processPayment(PaymentDTO $dto): array
  {
    return $this->processAction->execute($dto);
  }

  public function payInstallment(PayInstallmentDTO $dto): array
  {
    return $this->installmentAction->execute($dto);
  }

  // public function processRefund(RefundDTO $dto): array
  // {
  //   return $this->refundAction->execute($dto);
  // }
  public function processRefund(RefundDTO $dto): array
  {
    return DB::transaction(function () use ($dto) {

      // 1. جلب payment
      $payment = $this->findPayment->execute($dto->paymentId);

      // 2. validation
      $this->validateRefund->execute($payment, $dto);

      // 3. build context (🔥 أهم خطوة)
      $dto = $this->buildContext->execute($payment, $dto);

      // 4. تحديد النوع
      if ($payment->gateway === 'wallet') {
        return $this->refundViaWallet->execute($payment, $dto);
      }

      return $this->refundViaGateway->execute($payment, $dto);
    });
  }

  public function topUp(TopUpDTO $dto): array
  {
    return $this->topUpActon->execute($dto);
  }
}
