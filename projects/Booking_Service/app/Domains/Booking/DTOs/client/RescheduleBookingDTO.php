<?php

namespace App\Domains\Booking\DTOs\Client;

class RescheduleBookingDTO
{
  public function __construct(
    public int $bookingId,
    public int $userId,
    public string $startAt,
    public string $endAt,
  ) {}

  public static function fromRequest($request): self
  {
    $user = $request->attributes->get('auth_user');

    return new self(
      bookingId: $request->booking_id,
      userId: $user['id'],
      startAt: $request->start_at,
      endAt: $request->end_at,
    );
  }
}
