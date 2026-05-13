<?php

namespace App\Domains\Subscription\Repositories\Eloquent;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use App\Models\SubscriptionUsage;
use Illuminate\Support\Facades\DB;

class EloquentSubscriptionRepository
implements SubscriptionRepositoryInterface
{
  public function create(
    SubscribeUserDTO $dto,
    SubscriptionPlan $plan,
    ?int $paymentId
  ): Subscription {

    return DB::transaction(function () use (
      $dto,
      $plan,
      $paymentId
    ) {

      $startsAt = now();

      $endsAt = now()->addDays(
        $plan->duration_days
      );

      return Subscription::create([

        'user_id' => $dto->userId,

        'project_id' => $plan->project_id,

        'plan_id' => $plan->id,

        'payment_id' => $paymentId,

        'status' => Subscription::STATUS_ACTIVE,

        'starts_at' => $startsAt,

        'ends_at' => $endsAt,

        'current_period_start' => $startsAt,

        'current_period_end' => $endsAt,

        'auto_renew' => $dto->autoRenew,

        'metadata' => $dto->metadata
      ]);
    });
  }

  public function hasActiveSubscription(
    int $userId,
    ?int $projectId
  ): bool {

    return Subscription::query()

      ->where('user_id', $userId)

      ->where('project_id', $projectId)

      ->where('status', Subscription::STATUS_ACTIVE)

      ->where('ends_at', '>', now())

      ->exists();
  }

  public function renew(
    Subscription $subscription,
    array $data
  ): Subscription {

    return DB::transaction(function () use (
      $subscription,
      $data
    ) {

      $subscription->update($data);

      return $subscription->fresh();
    });
  }
  public function cancel(
    Subscription $subscription,
    array $data
  ): Subscription {

    return DB::transaction(function () use (
      $subscription,
      $data
    ) {

      $subscription->update($data);

      return $subscription->fresh();
    });
  }
  public function findActiveSubscription(
    int $userId,
    ?int $projectId
  ): ?Subscription {

    return Subscription::query()

      ->where('user_id', $userId)

      ->where('project_id', $projectId)

      ->where('status', Subscription::STATUS_ACTIVE)

      ->where('ends_at', '>', now())

      ->with([
        'plan.features'
      ])

      ->first();
  }


  public function getFeatureUsage(
    int $subscriptionId,
    string $featureKey
  ): int {

    return SubscriptionUsage::query()

      ->where('subscription_id', $subscriptionId)

      ->where('feature_key', $featureKey)

      ->value('used_value')

      ?? 0;
  }

  // public function incrementFeatureUsage(
  //   int $subscriptionId,
  //   string $featureKey,
  //   int $amount = 1
  // ): void {

  //   $usage = SubscriptionUsage::query()

  //     ->firstOrCreate(
  //       [
  //         'subscription_id' => $subscriptionId,
  //         'feature_key' => $featureKey
  //       ],
  //       [
  //         'used_value' => 0
  //       ]
  //     );

  //   $usage->increment(
  //     'used_value',
  //     $amount
  //   );
  // }

  public function incrementFeatureUsage(
    int $subscriptionId,
    string $featureKey,
    int $amount = 1,
    ?string $resetAt = null
  ): void {

    DB::table('subscription_usages')

      ->updateOrInsert(

        [
          'subscription_id' => $subscriptionId,
          'feature_key' => $featureKey
        ],

        [

          'used_value' => DB::raw(
            "used_value + {$amount}"
          ),

          'reset_at' => $resetAt,

          'updated_at' => now(),

          'created_at' => now()
        ]
      );
  }

  public function resetUsage(
    int $subscriptionId,
    string $featureKey,
    ?string $nextResetAt
  ): void {

    DB::table('subscription_usages')

      ->where('subscription_id', $subscriptionId)

      ->where('feature_key', $featureKey)

      ->update([

        'used_value' => 0,

        'reset_at' => $nextResetAt,

        'updated_at' => now()
      ]);
  }

  
}
