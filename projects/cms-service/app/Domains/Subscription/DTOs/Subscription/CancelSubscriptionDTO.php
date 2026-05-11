<?php

namespace App\Domains\Subscription\DTOs\Subscription;

use App\Models\Subscription;
use App\Domains\Subscription\Requests\Subscription\CancelSubscriptionRequest;

class CancelSubscriptionDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly Subscription $subscription,
        public readonly ?string $reason
    ) {}

    public static function fromRequest(
        CancelSubscriptionRequest $request,
        Subscription $subscription
    ): self {

        $user = request()
            ->attributes
            ->get('auth_user');

        return new self(
            userId: $user['id'],
            subscription: $subscription,
            reason: $request->reason
        );
    }
}