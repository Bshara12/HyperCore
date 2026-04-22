<?php

namespace App\Domains\Booking\Actions\Client;

class ValidateBookingTimeAction
{
  public function execute($start, $end): void
  {
    if ($start >= $end) {
      throw new \Exception('Invalid time range');
    }

    if ($start->isPast()) {
      throw new \Exception('Cannot book past time');
    }
  }
}
