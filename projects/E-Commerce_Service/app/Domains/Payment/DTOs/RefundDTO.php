<?php

namespace App\Domains\Payment\DTOs;

class RefundDTO
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $gateway,
        public readonly ?string $reason = null,
        public readonly array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            paymentId: $data['payment_id'],
            transactionId: $data['transaction_id'],
            amount: (float) $data['amount'],
            currency: strtoupper($data['currency'] ?? 'USD'),
            gateway: $data['gateway'],
            reason: $data['reason'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ];
    }
}
