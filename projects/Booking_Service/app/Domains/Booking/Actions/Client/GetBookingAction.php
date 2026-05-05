<?php

namespace App\Domains\Booking\Actions\Client;

use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class GetBookingAction
{
    public function __construct(
        protected BookingRepositoryInterface $repo
    ) {}

    public function execute(int $id)
    {
        return Cache::remember(
            CacheKeys::booking($id),
            CacheKeys::TTL_SHORT,
            function () use ($id) {
                $booking = $this->repo->findById($id);

                if (! $booking) {
                    throw new \Exception('Booking not found');
                }

                return $booking;
            }
        );
    }
}
