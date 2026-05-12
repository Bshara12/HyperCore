<?php

namespace App\Domains\E_Commerce\DTOs\ReturnRequest;

class GetReturnRequestsDTO
{
    public function __construct(
        public int $project_id
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            project_id: $request->project_id // 🔥 جاي من middleware
        );
    }
}
