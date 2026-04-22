<?php

namespace App\Domains\Booking\Read\DTOs;

use App\Domains\Booking\Requests\GetResourceBookingsRequest;

class GetResourceBookingsDTO
{
  public function __construct(
    public readonly int $resourceId,
    public readonly ?string $status,
    public readonly ?string $from,
    public readonly ?string $to,
  ) {}

  public static function fromRequest(int $resourceId, GetResourceBookingsRequest $request): self
  {
    return new self(
      resourceId: $resourceId,
      status: $request->status ?? null,
      from: $request->from ?? null,
      to: $request->to ?? null,
    );
  }
}
