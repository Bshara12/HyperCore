<?php

namespace App\Domains\Booking\Actions\Client;

use App\Domains\Booking\DTOs\Client\CreateBookingDTO;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Models\Booking;

class CreateBookingRecordAction
{
  public function __construct(
    protected BookingRepositoryInterface $bookingRepository
  ) {}

  public function execute(CreateBookingDTO $dto)
  {
    return $this->bookingRepository->create([
      'resource_id' => $dto->resourceId,
      'user_id'     => $dto->userId,
      'project_id'  => $dto->projectId,
      'start_at'    => $dto->startAt,
      'end_at'      => $dto->endAt,
      'status'      => Booking::STATUS_PENDING,
      'amount'      => $dto->amount,
      'currency'    => $dto->currency,
    ]);
  }
}
