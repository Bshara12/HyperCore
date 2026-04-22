<?php

namespace App\Domains\Booking\Actions\Client;

use App\Services\CMS\CMSApiClient;

// class ProcessRefundAction
// {
//   public function __construct(
//     protected CMSApiClient $cmsClient
//   ) {}

//   public function execute($booking, float $amount): void
//   {
//     $this->cmsClient->refundBooking([
//       'payment_id' => $booking->payment_id,
//       'amount'     => $amount,
//     ]);
//   }
// }
class ProcessRefundAction
{
  public function __construct(
    protected CMSApiClient $cmsClient
  ) {}

  public function execute($booking, float $amount): void
  {
    // 🔥 1. إذا ما في دفع → لا تعمل شي
    if (!$booking->payment_id) {
      return;
    }

    // 🔥 2. إذا المورد مجاني → لا refund
    if ($booking->resource->payment_type === 'free') {
      return;
    }

    // 🔥 3. إذا المبلغ صفر → لا داعي
    if ($amount <= 0) {
      return;
    }

    // 🔥 4. call CMS
    $this->cmsClient->refundBooking([
      'payment_id' => $booking->payment_id,
      'amount'     => $amount,
    ]);
  }
}
