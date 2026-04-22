<?php

namespace App\Domains\Booking\Actions\Client;

class ValidateCancelableAction
{
  public function execute($booking): void
  {
    if ($booking->status === 'cancelled') {
      throw new \Exception('Already cancelled');
    }

    if ($booking->status === 'completed') {
      throw new \Exception('Already completed');
    }

    if (\Carbon\Carbon::parse($booking->start_at)->isPast()) {
      throw new \Exception('Cannot cancel past booking');
    }
  }
}
