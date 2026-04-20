<?php

namespace App\Domains\CMS\DTOs\Rate;

class GetRatingsDTO
{
    public function __construct(
        public string $rateableType,
        public int $rateableId,
        public int $perPage = 10
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            rateableType: $request->rateable_type,
            rateableId: $request->rateable_id,
            perPage: $request->get('per_page', 10)
        );
    }
}