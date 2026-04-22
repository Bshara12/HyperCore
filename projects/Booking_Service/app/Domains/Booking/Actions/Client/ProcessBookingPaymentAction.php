<?php

namespace App\Domains\Booking\Actions\Client;

use App\Domains\Booking\DTOs\Client\CreateBookingDTO;
use App\Services\CMS\CMSApiClient;

class ProcessBookingPaymentAction
{
  public function __construct(
    protected CMSApiClient $cmsClient
  ) {}

  // public function execute($booking, CreateBookingDTO $dto)
  // {
  //   try {
  //     $payment = $this->cmsClient->chargeBooking([
  //       'user_id'   => $dto->userId,
  //       'user_name' => $dto->userName,
  //       'project_id' => $dto->projectId,
  //       'amount'    => $dto->amount,
  //       'currency'  => $dto->currency,
  //       'gateway'   => $dto->gateway,
  //       'token'     => $dto->gatewayToken,
  //     ]);

  //     $booking->update([
  //       'status' => \App\Models\Booking::STATUS_CONFIRMED,
  //       'payment_id' => $payment['payment_id'] ?? null,
  //     ]);
  //   } catch (\Throwable $e) {
  //     $booking->update(['status' => \App\Models\Booking::STATUS_CANCELLED]);
  //     throw $e;
  //   }

  //   return $booking;
  // }
  public function execute($booking, CreateBookingDTO $dto)
{
    // 🔥 1. إذا مجاني → بدون دفع
    if ($booking->resource->payment_type === 'free') {

        $booking->update([
            'status' => \App\Models\Booking::STATUS_CONFIRMED,
            'payment_id' => null,
        ]);

        return $booking;
    }

    try {
        $payment = $this->cmsClient->chargeBooking([
            'user_id'   => $dto->userId,
            'user_name' => $dto->userName,
            'project_id' => $dto->projectId,
            'amount'    => $dto->amount,
            'currency'  => $dto->currency,
            'gateway'   => $dto->gateway,
            'token'     => $dto->gatewayToken,
        ]);

        $booking->update([
            'status' => \App\Models\Booking::STATUS_CONFIRMED,
            'payment_id' => $payment['payment_id'] ?? null,
        ]);

    } catch (\Throwable $e) {
        $booking->update([
            'status' => \App\Models\Booking::STATUS_CANCELLED
        ]);

        throw $e;
    }

    return $booking;
}
}
