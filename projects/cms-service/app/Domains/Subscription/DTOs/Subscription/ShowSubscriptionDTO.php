<?php

namespace App\Domains\Subscription\DTOs\Subscription;

use App\Models\Subscription;

class ShowSubscriptionDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly Subscription $subscription
    ) {}

    public static function fromSubscription(
        Subscription $subscription
    ): self {

        $user = request()
            ->attributes
            ->get('auth_user');

        return new self(
            userId: $user['id'],
            subscription: $subscription
        );
    }
}