<?php

namespace App\Domains\Booking\Actions\Client;

class UpdateBookingStatusAction
{
  public function execute($booking, float $refundAmount)
  {
    $booking->update([
      'status' => 'cancelled',
      'refund_amount' => $refundAmount,
      'cancellation_reason' => 'Cancelled by user',
    ]);

    return $booking;
  }
}
