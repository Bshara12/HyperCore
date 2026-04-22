<?php

namespace App\Domains\Booking\Actions\Client;

class UpdateBookingTimeAction
{
  public function execute($booking, $start, $end)
  {
    $booking->update([
      'start_at' => $start,
      'end_at'   => $end,
    ]);

    return $booking;
  }
}
