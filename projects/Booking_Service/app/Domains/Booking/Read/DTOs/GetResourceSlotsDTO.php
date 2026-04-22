<?php

namespace App\Domains\Booking\Read\DTOs;

use App\Domains\Booking\Requests\GetSlotsRequest;

class GetResourceSlotsDTO
{
  public function __construct(
    public readonly int $resourceId,
    public readonly string $date,
  ) {}

  public static function fromRequest(int $resourceId, GetSlotsRequest $request): self
  {
    return new self(
      resourceId: $resourceId,
      date: $request->date,
    );
  }
}
