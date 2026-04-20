<?php

namespace App\Domains\CMS\DTOs\Rate;

class GetRatingStatsDTO
{
  public function __construct(
    public string $rateableType,
    public int $rateableId
  ) {}

  public static function fromRequest($request): self
  {
    return new self(
      rateableType: $request->rateable_type,
      rateableId: $request->rateable_id
    );
  }
}
