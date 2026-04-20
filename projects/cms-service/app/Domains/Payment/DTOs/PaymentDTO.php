<?php

namespace App\Domains\Payment\DTOs;

use App\Models\Wallet;
use Illuminate\Http\Request;

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
    public readonly ?Wallet $toWallet          = null,
    public readonly ?string $description       = null,
    public readonly ?float  $downPayment       = null,
    public readonly ?float  $installmentAmount = null,
    public readonly ?int    $totalInstallments = null,
    public readonly ?int    $intervalDays      = 30,
  ) {}

  public static function fromRequest(Request $request): self
  {
    $wallet = null;
    if ($request->toWallet) {
      $wallet = Wallet::where('wallet_number', $request->toWallet)->firstOrFail();
    }
    return new self(
      userId: $request->userId,
      userName: $request->userName,
      projectId: $request->projectId,
      amount: $request->amount,
      currency: $request->currency,
      gateway: $request->gateway,
      paymentType: $request->paymentType,
      toWallet: $wallet,
      description: $request->description,
      downPayment: $request->downPayment,
      installmentAmount: $request->installmentAmount,
      totalInstallments: $request->totalInstallments,
      intervalDays: $request->intervalDays,
    );
  }

  public static function fromArray(array $data): self
  {
    return new self(
      userId: $data['user_id'],
      userName: $data['user_name'],
      projectId: $data['project_id'],
      amount: $data['amount'],
      currency: strtoupper($data['currency'] ?? 'USD'),
      gateway: $data['gateway'],
      paymentType: $data['payment_type'] ?? 'full',
      toWallet: isset($data['to_wallet_id']) ? $data['to_wallet_id'] : null,
      description: $data['description'] ?? null,
      downPayment: isset($data['down_payment']) ? $data['down_payment'] : null,
      installmentAmount: isset($data['installment_amount']) ? $data['installment_amount'] : null,
      totalInstallments: isset($data['total_installments']) ? $data['total_installments'] : null,
      intervalDays: isset($data['interval_days']) ? $data['interval_days'] : 30,
    );
  }

  public function isInstallment(): bool
  {
    return $this->paymentType === 'installment';
  }
}
