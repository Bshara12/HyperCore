<?php

namespace App\Domains\Booking\DTOs;

class AvailabilityDTO
{
  public function __construct(
    public readonly int    $resourceId,
    public readonly int    $dayOfWeek,
    public readonly string $startTime,
    public readonly string $endTime,
    public readonly int    $slotDuration,
    public readonly bool   $isActive = true,
  ) {}

  public static function fromArray(array $data, int $resourceId): self
  {
    return new self(
      resourceId: $resourceId,
      dayOfWeek: $data['day_of_week'],
      startTime: $data['start_time'],
      endTime: $data['end_time'],
      slotDuration: $data['slot_duration'],
      isActive: $data['is_active'] ?? true,
    );
  }
}
