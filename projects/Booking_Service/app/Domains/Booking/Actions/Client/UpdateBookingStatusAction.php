<?php

namespace App\Domains\Booking\Actions\Client;

use App\Domains\Booking\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class UpdateBookingStatusAction
{
  public function execute($booking, float $refundAmount)
  {
    $booking->update([
      'status' => 'cancelled',
      'refund_amount' => $refundAmount,
      'cancellation_reason' => 'Cancelled by user',
    ]);
    Cache::forget(CacheKeys::booking($booking->id));
    Cache::forget(CacheKeys::userBookings($booking->user_id));
    Cache::tags(["resource_{$booking->resource_id}_bookings"])->flush();
    return $booking;
  }
}
