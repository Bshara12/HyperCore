<?php

namespace App\Domains\Payment\DTOs;

use App\Domains\Payment\Requests\PayInstallmentRequest;
use App\Models\Wallet;

class PayInstallmentDTO
{
  public function __construct(
    public readonly int     $paymentId,
    public readonly string  $gateway,
    public readonly string  $currency,
    public readonly ?Wallet $toWallet = null
  ) {}

  public static function fromRequest(PayInstallmentRequest $request): self
  {
    $wallet = null;
    if ($request->to_wallet_number) {
      $wallet = Wallet::where('wallet_number', $request->to_wallet_number)->firstOrFail();
    }
    return new self(
      paymentId: $request->payment_id,
      gateway: $request->gateway,
      currency: $request->currency,
      toWallet: $wallet,
    );
  }
}
