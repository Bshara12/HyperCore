<?php

namespace App\Domains\Booking\Actions\Client;

use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;

class GetBookingAction
{
  public function __construct(
    protected BookingRepositoryInterface $repo
  ) {}

  public function execute(int $id)
  {
    $booking = $this->repo->findById($id);

    if (!$booking) {
      throw new \Exception('Booking not found');
    }

    return $booking;
  }
}
