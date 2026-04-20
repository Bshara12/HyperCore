<?php

namespace App\Domains\Payment\DTOs;

use App\Domains\Payment\Requests\RefundRequest;

class RefundDTO
{
  public function __construct(
    public readonly int     $paymentId,
    public readonly string  $gateway,
    public readonly float   $amount,
    public readonly string  $currency,
    public readonly ?string $reason        = null,
    public readonly ?string $transactionId = null,
    public readonly ?int    $fromWalletId  = null,
    public readonly ?int    $toWalletId    = null,
    public readonly array   $metadata      = [],
  ) {}

  // public static function fromRequest(RefundRequest $request): self
  // {
  //   return new self(
  //     paymentId: (int)   $request->payment_id,
  //     gateway: $request->gateway,
  //     amount: (float) $request->amount,
  //     currency: strtoupper($request->currency),
  //     reason: $request->reason ?? null,
  //     transactionId: $request->transaction_id ?? null,
  //     fromWalletId: $request->from_wallet_id ? (int) $request->from_wallet_id : null,
  //   );
  // }


  public static function fromRequest(RefundRequest $request): self
  {
    return new self(
      paymentId: (int) $request->payment_id,
      gateway: '', // سيتم تعبئته لاحقاً
      amount: (float) $request->amount,
      currency: '', // سيتم تعبئته لاحقاً
      reason: $request->reason ?? null,
    );
  }


  public static function fromArray(array $data): self
  {
    return new self(
      paymentId: (int)   $data['payment_id'],
      gateway: $data['gateway'],
      amount: (float) $data['amount'],
      currency: strtoupper($data['currency'] ?? 'USD'),
      reason: $data['reason'] ?? null,
      transactionId: $data['transaction_id'] ?? null,
      fromWalletId: isset($data['from_wallet_id']) ? (int) $data['from_wallet_id'] : null,
      metadata: $data['metadata'] ?? [],
    );
  }
}
