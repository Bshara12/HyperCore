<?php

namespace App\Domains\Subscription\Repositories\Interface;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;

interface SubscriptionRepositoryInterface
{
  public function create(
    SubscribeUserDTO $dto,
    SubscriptionPlan $plan,
    ?int $paymentId
  ): Subscription;

  public function hasActiveSubscription(
    int $userId,
    ?int $projectId
  ): bool;
  public function renew(
    Subscription $subscription,
    array $data
  ): Subscription;

  public function cancel(
    Subscription $subscription,
    array $data
  ): Subscription;

  public function findActiveSubscription(
    int $userId,
    ?int $projectId
  ): ?Subscription;

  public function getFeatureUsage(
    int $subscriptionId,
    string $featureKey
  ): int;

  // public function incrementFeatureUsage(
  //   int $subscriptionId,
  //   string $featureKey,
  //   int $amount = 1
  // ): void;
  public function incrementFeatureUsage(
    int $subscriptionId,
    string $featureKey,
    int $amount = 1,
    ?string $resetAt = null
  ): void;
  public function resetUsage(
    int $subscriptionId,
    string $featureKey,
    ?string $nextResetAt
  ): void;
}
