<?php

namespace App\Domains\Booking\DTOs\Client;

class CancelBookingDTO
{
  public function __construct(
    public int $bookingId,
    public int $userId,
  ) {}

  public static function fromRequest($request): self
  {
    $user = $request->attributes->get('auth_user');

    if (!$user) {
      throw new \Exception('Unauthenticated');
    }

    return new self(
      bookingId: $request->booking_id,
      userId: $user['id'],
    );
  }
}
