<?php

namespace App\Domains\Subscription\DTOs\Subscription;

use App\Domains\Subscription\Requests\Subscription\SubscribeUserRequest;

class SubscribeUserDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly int $planId,
        public readonly string $gateway,
        public readonly string $paymentType,
        public readonly bool $autoRenew,
        public readonly ?array $metadata
    ) {}

    public static function fromRequest(
        SubscribeUserRequest $request
    ): self {

        $user = request()
            ->attributes
            ->get('auth_user');

        return new self(
            userId: $user['id'],
            userName: $user['name'],
            planId: $request->plan_id,
            gateway: $request->gateway,
            paymentType: $request->payment_type,
            autoRenew: $request->boolean(
                'auto_renew',
                true
            ),
            metadata: $request->metadata
        );
    }
}