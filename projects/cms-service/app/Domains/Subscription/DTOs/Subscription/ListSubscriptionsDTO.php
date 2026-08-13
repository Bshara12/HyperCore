<?php

namespace App\Domains\Subscription\DTOs\Subscription;

// use App\Domains\Subscription\Requests\Subscription\ListSubscriptionsRequest;
use App\Domains\Subscription\Requests\Subscription\ListSubscriptionsRequest;

class ListSubscriptionsDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $projectId,
        public readonly ?string $status
    ) {}

    public static function fromRequest(
        ListSubscriptionsRequest $request
    ): self {

        // نفس نمط CancelSubscriptionDTO —
        // الـ userId دايمًا من auth_user، أي user_id يجي بالـ query يُتجاهل عمدًا.
        $user = request()
            ->attributes
            ->get('auth_user');

        return new self(
            userId: $user['id'],
            projectId: $request->project_id
                ? (int) $request->project_id
                : null,
            status: $request->status
        );
    }
}