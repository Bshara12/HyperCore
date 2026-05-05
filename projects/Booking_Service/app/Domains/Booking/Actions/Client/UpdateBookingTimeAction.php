<?php

namespace App\Domains\Booking\Actions\Client;

use App\Domains\Booking\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class UpdateBookingTimeAction
{
    public function execute($booking, $start, $end)
    {
        $booking->update([
            'start_at' => $start,
            'end_at' => $end,
        ]);
        Cache::forget(CacheKeys::booking($booking->id));
        Cache::tags(["resource_{$booking->resource_id}_bookings"])->flush();

        return $booking;
    }
}
