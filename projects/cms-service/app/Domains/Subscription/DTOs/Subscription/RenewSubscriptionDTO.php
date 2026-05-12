<?php

namespace App\Domains\Subscription\DTOs\Subscription;

use App\Models\Subscription;
use App\Domains\Subscription\Requests\Subscription\RenewSubscriptionRequest;

class RenewSubscriptionDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly Subscription $subscription,
        public readonly string $gateway,
        public readonly string $paymentType,
        public readonly ?bool $autoRenew,
        public readonly ?array $metadata
    ) {}

    public static function fromRequest(
        RenewSubscriptionRequest $request,
        Subscription $subscription
    ): self {

        $user = request()
            ->attributes
            ->get('auth_user');

        return new self(
            userId: $user['id'],
            userName: $user['name'],
            subscription: $subscription,
            gateway: $request->gateway,
            paymentType: $request->payment_type,
            autoRenew: $request->auto_renew,
            metadata: $request->metadata
        );
    }
}