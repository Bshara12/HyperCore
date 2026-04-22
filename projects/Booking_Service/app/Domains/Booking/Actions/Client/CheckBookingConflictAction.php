<?php

namespace   App\Domains\Booking\Actions\Client;

use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;

class CheckBookingConflictAction
{
  public function __construct(
    protected BookingRepositoryInterface $bookingRepository
  ) {}

  public function execute(
    int $resourceId,
    $start,
    $end,
    int $capacity,
    ?int $ignoreBookingId,
  ): void {
    // $count = $this->bookingRepository
    //   ->countConflictingBookings($resourceId, $start, $end);

    // if ($count >= $capacity) {
    //   throw new \Exception('Slot is fully booked');
    // }
      $count = $this->bookingRepository
        ->countConflictingBookings(
            $resourceId,
            $start,
            $end,
            $ignoreBookingId
        );

    if ($count >= $capacity) {
        throw new \Exception('Slot is fully booked');
    }
  }
}
