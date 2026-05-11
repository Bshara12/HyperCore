<?php

namespace App\Domains\Subscription\Actions\Usage;

use Exception;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionFeatureRule;
use App\Domains\Subscription\DTOs\Usage\ProcessUsageEventDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use App\Domains\Subscription\Repositories\Interface\SubscriptionFeatureRuleRepositoryInterface;
use App\Models\Subscription;

class ProcessUsageEventAction
{
  public function __construct(

    private SubscriptionRepositoryInterface $subscriptionRepository,

    private SubscriptionFeatureRuleRepositoryInterface $ruleRepository
  ) {}

  public function execute(
    ProcessUsageEventDTO $dto
  ): void {

    $rules = $this->ruleRepository
      ->findActiveRulesByEvent(
        $dto->projectId,
        $dto->eventKey
      );

    if ($rules->isEmpty()) {
      return;
    }

    $subscription = $this->subscriptionRepository
      ->findActiveSubscription(
        $dto->userId,
        $dto->projectId
      );

    // if (!$subscription) {

    //   throw new Exception(
    //     'No active subscription.'
    //   );
    // }

    foreach ($rules as $rule) {

      $this->processRule(
        subscription: $subscription,
        rule: $rule,
        amount: $dto->amount
      );
    }
  }

  // ─────────────────────────────────────

  private function processRule(
    Subscription  $subscription,
    SubscriptionFeatureRule $rule,
    int $amount
  ): void {

    $feature = $subscription
      ->plan
      ->features
      ->firstWhere(
        'feature_key',
        $rule->feature_key
      );

    if (!$feature) {

      throw new Exception(
        sprintf(
          'Feature [%s] missing in plan.',
          $rule->feature_key
        )
      );
    }

    match ($rule->action) {

      SubscriptionFeatureRule::ACTION_CHECK =>
      $this->checkLimit(
        $subscription->id,
        $feature,
        $amount
      ),

      SubscriptionFeatureRule::ACTION_INCREMENT =>
      // $this->incrementUsage(
      //   $subscription->id,
      //   $feature->feature_key,
      //   $amount
      // ),
      $this->incrementUsage(

        $subscription->id,

        $rule,

        $feature->feature_key,

        $amount
      ),

      SubscriptionFeatureRule::ACTION_BOTH =>
      $this->checkAndIncrement(

        $subscription->id,

        $rule,

        $feature,

        $amount
      ),

      default => null
    };
  }

  // ─────────────────────────────────────

  private function checkAndIncrement(
    int $subscriptionId,
    SubscriptionFeatureRule $rule,
    SubscriptionFeature $feature,
    int $amount
  ): void {

    $this->checkLimit(
      $subscriptionId,
      $feature,
      $amount
    );

    // $this->incrementUsage(
    //   $subscriptionId,
    //   $feature->feature_key,
    //   $amount
    // );
    $this->incrementUsage(

      $subscriptionId,

      $rule,

      $feature->feature_key,

      $amount
    );
  }

  // ─────────────────────────────────────

  private function checkLimit(
    int $subscriptionId,
    SubscriptionFeature $feature,
    int $amount
  ): void {

    if (
      $feature->feature_type !== 'number'
    ) {
      return;
    }

    $limit = (int)
    $feature->feature_value;

    $used = $this->subscriptionRepository
      ->getFeatureUsage(
        $subscriptionId,
        $feature->feature_key
      );

    if (
      ($used + $amount) > $limit
    ) {

      throw new Exception(
        sprintf(
          'Feature limit exceeded [%s].',
          $feature->feature_key
        )
      );
    }
  }

  // ─────────────────────────────────────

  // private function incrementUsage(
  //   int $subscriptionId,
  //   string $featureKey,
  //   int $amount
  // ): void {

  //   $this->subscriptionRepository
  //     ->incrementFeatureUsage(
  //       $subscriptionId,
  //       $featureKey,
  //       $amount
  //     );
  // }

  private function incrementUsage(
    int $subscriptionId,
    SubscriptionFeatureRule $rule,
    string $featureKey,
    int $amount
  ): void {

    $resetAt = match ($rule->reset_type) {

      SubscriptionFeatureRule::RESET_DAILY =>
      now()->addDay(),

      SubscriptionFeatureRule::RESET_MONTHLY =>
      now()->addMonth(),

      SubscriptionFeatureRule::RESET_YEARLY =>
      now()->addYear(),

      default => null
    };

    $this->subscriptionRepository
      ->incrementFeatureUsage(

        subscriptionId: $subscriptionId,

        featureKey: $featureKey,

        amount: $amount,

        resetAt: $resetAt
      );
  }
}




/*
كيفية الاستخدام
app(DomainEventService::class)
    ->dispatch(

        userId: $userId,

        projectId: $projectId,

        eventKey: sprintf(
            '%s.create',
            $dataType->slug
        )
    );
    
*/