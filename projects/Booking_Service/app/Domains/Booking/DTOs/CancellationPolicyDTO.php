<?php

namespace App\Domains\Booking\DTOs;

class CancellationPolicyDTO
{
  public function __construct(
    public readonly int     $resourceId,
    public readonly int     $hoursBefore,
    public readonly int     $refundPercentage,
    public readonly ?string $description = null,
  ) {}

  public static function fromArray(array $data, int $resourceId): self
  {
    return new self(
      resourceId: $resourceId,
      hoursBefore: $data['hours_before'],
      refundPercentage: $data['refund_percentage'],
      description: $data['description'] ?? null,
    );
  }
}
