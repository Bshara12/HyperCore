<?php

namespace App\Domains\Subscription\DTOs\Plan;

use App\Domains\Subscription\Requests\Plan\ListPlansRequest;

class ListPlansDTO
{
    public function __construct(
        public readonly ?int $projectId
    ) {}

    public static function fromRequest(
        ListPlansRequest $request
    ): self {

        return new self(
            projectId: $request->project_id
                ? (int) $request->project_id
                : null
        );
    }
}