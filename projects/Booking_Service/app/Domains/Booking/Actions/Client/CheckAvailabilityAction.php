<?php

namespace App\Domains\Booking\Actions\Client;

use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;

class CheckAvailabilityAction
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository
    ) {}

    public function execute(int $resourceId, $start, $end): void
    {
        $dayOfWeek = $start->dayOfWeek;

        $availabilities = $this->bookingRepository
            ->getAvailabilitiesForDay($resourceId, $dayOfWeek);

        if ($availabilities->isEmpty()) {
            throw new \Exception('No availability for this day');
        }

        foreach ($availabilities as $availability) {

            $startTime = \Carbon\Carbon::parse($availability->start_time);
            $endTime   = \Carbon\Carbon::parse($availability->end_time);

            $bookingStart = \Carbon\Carbon::parse($start->format('H:i:s'));
            $bookingEnd   = \Carbon\Carbon::parse($end->format('H:i:s'));

            if ($bookingStart >= $startTime && $bookingEnd <= $endTime) {
                return;
            }
        }

        throw new \Exception('Time out of times availability');
    }
}