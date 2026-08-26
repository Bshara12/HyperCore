<?php

namespace App\Domains\Subscription\Repositories\Eloquent;

use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUsage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentSubscriptionRepository implements SubscriptionRepositoryInterface
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

        'metadata' => $dto->metadata,
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
        'plan.features',
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

  public function incrementFeatureUsage(
    int $subscriptionId,
    string $featureKey,
    int $amount = 1,
    DateTimeInterface|string|null $resetAt = null
  ): void {
    // 1. التأكد من وجود السجل أو إنشاؤه بقيمة افتراضية 0
    $usage = SubscriptionUsage::firstOrCreate(
      [
        'subscription_id' => $subscriptionId,
        'feature_key' => $featureKey,
      ],
      [
        'used_value' => 0,
        'reset_at' => $resetAt,
      ]
    );

    /*
    | firstOrCreate only applies the defaults when the row is created.
    | An existing row whose reset_at was never set (rule created before
    | the row, or rule switched from `never` to a periodic reset_type)
    | would otherwise never be picked up by the reset scheduler.
    */
    if ($resetAt !== null && $usage->reset_at === null) {

      $usage->reset_at = Carbon::parse($resetAt);

      $usage->save();
    }

    // 2. زيادة القيمة بأمان
    $usage->increment('used_value', $amount);
  }

  public function resetUsage(
    int $subscriptionId,
    string $featureKey,
    DateTimeInterface|string|null $nextResetAt
  ): void {

    DB::table('subscription_usages')
      ->where('subscription_id', $subscriptionId)
      ->where('feature_key', $featureKey)
      ->update([

        'used_value' => 0,

        'reset_at' => $nextResetAt,

        'updated_at' => now(),
      ]);
  }


   public function findForUser(
    int $userId,
    ?int $projectId,
    ?string $status
  ): Collection {

    return Subscription::query()
      ->where('user_id', $userId)
      ->when(
        $projectId,
        fn ($query) => $query->where('project_id', $projectId)
      )
      ->when(
        $status,
        fn ($query) => $query->where('status', $status)
      )
      ->with('plan')
      ->latest()
      ->get();
  }

  public function findByIdWithUsages(
    int $id
  ): ?Subscription {

    return Subscription::query()
      ->with([
        'plan',
        'usages',
      ])
      ->find($id);
  }

  // ─── Admin (project-scoped) ───────────────────────────────────────

  /**
   * Every subscription of one project, for the admin dashboard.
   *
   * Unlike findForUser() this is NOT filtered by user_id — the caller is
   * an operator looking at the project's subscriber list, so the project
   * scope is what keeps the result set bounded.
   */
  public function paginateForProject(
    int $projectId,
    ?string $status,
    ?int $planId,
    ?int $userId,
    int $perPage
  ): LengthAwarePaginator {

    return Subscription::query()
      ->where('project_id', $projectId)
      ->when(
        $status,
        fn ($query) => $query->where('status', $status)
      )
      ->when(
        $planId,
        fn ($query) => $query->where('plan_id', $planId)
      )
      ->when(
        $userId,
        fn ($query) => $query->where('user_id', $userId)
      )
      ->with([
        'plan',
        'usages',
      ])
      ->latest()
      ->paginate($perPage);
  }

  /**
   * Subscription count per status for one project, e.g.
   * ['active' => 12, 'cancelled' => 3].
   *
   * One grouped query instead of one COUNT per status.
   *
   * @return array<string, int>
   */
  public function statusCountsForProject(
    int $projectId
  ): array {

    return Subscription::query()
      ->where('project_id', $projectId)
      ->groupBy('status')
      ->selectRaw('status, COUNT(*) as total')
      ->pluck('total', 'status')
      ->map(fn ($total) => (int) $total)
      ->all();
  }
}
