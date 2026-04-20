<?php

namespace App\Domains\Payment\DTOs;

use App\Domains\Payment\Requests\ProcessPaymentRequest;

class PaymentDTO
{
  public function __construct(
    public readonly int     $userId,
    public readonly string  $userName,
    public readonly int     $projectId,
    public readonly float   $amount,
    public readonly string  $currency,
    public readonly string  $gateway,
    public readonly string  $paymentType,
    public readonly ?string $toWallet          = null,
    public readonly ?string $description       = null,

    // حقول التقسيط — مطلوبة فقط إذا paymentType = installment
    public readonly ?float  $downPayment       = null,
    public readonly ?float  $installmentAmount = null,
    public readonly ?int    $totalInstallments = null,
    public readonly ?int    $intervalDays      = 30,
  ) {}

  public static function fromRequest(ProcessPaymentRequest $request): self
  {
    return new self(
      userId: $request->attributes->get('auth_user')['id'],
      userName: $request->attributes->get('auth_user')['name'],
      projectId: $request->project_id,
      amount: $request->amount,
      currency: strtoupper($request->currency),
      gateway: $request->gateway,
      paymentType: $request->payment_type,
      toWallet: $request->to_wallet_number ?? null,
      description: $request->description ?? null,
      downPayment: $request->down_payment ? $request->down_payment : null,
      installmentAmount: $request->installment_amount ? $request->installment_amount : null,
      totalInstallments: $request->total_installments ? $request->total_installments : null,
      intervalDays: $request->interval_days ? $request->interval_days : 30,
    );
  }
}
