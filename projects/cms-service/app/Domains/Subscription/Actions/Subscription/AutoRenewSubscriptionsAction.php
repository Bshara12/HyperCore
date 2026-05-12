<?php

namespace App\Domains\Subscription\Actions\Subscription;

use Throwable;
use App\Models\Payment;
use App\Models\Subscription;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Services\PaymentService;

class AutoRenewSubscriptionsAction
{
  public function __construct(
    private PaymentService $paymentService
  ) {}

  public function execute(): void
  {
    Subscription::query()

      ->where('auto_renew', true)

      ->whereIn('status', [
        Subscription::STATUS_ACTIVE,
        Subscription::STATUS_GRACE_PERIOD
      ])

      ->where('ends_at', '<=', now())

      ->with('plan')

      // ->chunkById(100, function ($subscriptions) {
      ->chunkById(
        100,
        function ($subscriptions): void {
          /** @var Subscription $subscription */

          foreach ($subscriptions as $subscription) {

            try {

              $this->renew($subscription);
            } catch (Throwable $e) {

              report($e);

              $subscription->update([
                'status' => Subscription::STATUS_GRACE_PERIOD
              ]);
            }
          }
        }
      );
  }

  private function renew(
    Subscription $subscription
  ): void {

    $plan = $subscription->plan;

    // FREE PLAN
    if ((float)$plan->price > 0) {

      $paymentDTO = PaymentDTO::fromArray([

        'user_id' => $subscription->user_id,

        'user_name' => 'Auto Renewal',

        'project_id' => $subscription->project_id,

        'amount' => $plan->price,

        'currency' => $plan->currency,

        'gateway' => 'wallet',

        'payment_type' => 'full',

        'description' => sprintf(
          'Auto renew subscription #%s',
          $subscription->id
        )
      ]);

      $result = $this->paymentService
        ->processPayment($paymentDTO);

      if (
        !$result['success']
      ) {

        throw new \Exception(
          'Auto renewal payment failed.'
        );
      }

      if (
        $result['status'] !== Payment::STATUS_PAID
        &&
        $result['status'] !== Payment::STATUS_PENDING
      ) {

        throw new \Exception(
          'Auto renewal payment incomplete.'
        );
      }
    }

    $newStart = now();

    $newEnd = now()->addDays(
      $plan->duration_days
    );

    $subscription->update([

      'status' => Subscription::STATUS_ACTIVE,

      'starts_at' => $subscription->starts_at,

      'ends_at' => $newEnd,

      'current_period_start' => $newStart,

      'current_period_end' => $newEnd
    ]);
  }
}
