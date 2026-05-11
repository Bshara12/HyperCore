<?php

namespace App\Domains\Payment\DTOs;

use App\Domains\Payment\Requests\PayInstallmentRequest;

class PayInstallmentDTO
{
    public function __construct(
        public readonly int $payment_id,
        public readonly string $gateway,
        public readonly string $currency,
        public readonly ?string $to_wallet_number = null,
    ) {}

    public static function fromRequest(PayInstallmentRequest $request): self
    {
        return new self(
            payment_id: $request->payment_id,
            gateway: $request->gateway,
            currency: strtoupper($request->currency),
            to_wallet_number: $request->to_wallet_number,
        );
    }
}
