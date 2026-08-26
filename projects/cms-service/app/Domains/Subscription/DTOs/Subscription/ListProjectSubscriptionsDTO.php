<?php

namespace App\Domains\Subscription\DTOs\Subscription;

use App\Domains\Subscription\Requests\Subscription\ListProjectSubscriptionsRequest;
use App\Support\CurrentProject;

class ListProjectSubscriptionsDTO
{
    public function __construct(
        public readonly int $projectId,
        public readonly ?string $status,
        public readonly ?int $planId,
        public readonly ?int $userId,
        public readonly int $perPage
    ) {}

    public static function fromRequest(
        ListProjectSubscriptionsRequest $request
    ): self {

        return new self(
            // Scope comes from the resolved project, never from user input.
            projectId: CurrentProject::id(),
            status: $request->status,
            planId: $request->plan_id
                ? (int) $request->plan_id
                : null,
            userId: $request->user_id
                ? (int) $request->user_id
                : null,
            perPage: (int) ($request->per_page ?? 25)
        );
    }
}
