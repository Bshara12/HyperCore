<?php

namespace App\Domains\Payment\Gateways;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;

interface PaymentGatewayInterface
{
  // الدفع الكامل
  public function charge(PaymentDTO $dto): array;

  // الدفع بمبلغ محدد — يُستخدم للتقسيط
  public function chargeAmount(PaymentDTO $dto, float $amount): array;

  public function refund(RefundDTO $dto): array;
  public function status(string $transactionId): array;
  public function getBalance(): array;
}
