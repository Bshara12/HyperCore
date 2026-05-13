<?php

namespace App\Domains\Subscription\Actions\Authorization;

use App\Models\Subscription;
use App\Exceptions\SubscriptionRequiredException;
use App\Exceptions\SubscriptionFeatureMissingException;
use App\Domains\Subscription\DTOs\Rule\AuthorizeEventDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use App\Domains\Subscription\Repositories\Interface\SubscriptionAccessRuleRepositoryInterface;

class AuthorizeEventAction
{
  public function __construct(

    private SubscriptionRepositoryInterface $subscriptionRepository,

    private SubscriptionAccessRuleRepositoryInterface $ruleRepository
  ) {}

  public function execute(
    AuthorizeEventDTO $dto
  ): void {

    $rule = $this->ruleRepository
      ->findActiveRuleByEvent(
        $dto->projectId,
        $dto->eventKey
      );

    // no rule = public access
    if (!$rule) {
      return;
    }

    // no subscription required
    if (!$rule->requires_subscription) {
      return;
    }

    $subscription = $this->subscriptionRepository
      ->findActiveSubscription(
        $dto->userId,
        $dto->projectId
      );
    if (!$subscription) {

      throw new SubscriptionRequiredException(
        'Active subscription required.'
      );
    }

    // no feature required
    if (!$rule->required_feature) {
      return;
    }

    $hasFeature = $subscription
      ->plan
      ->features
      ->contains(function ($feature) use ($rule) {

        if (
          $feature->feature_key
          !==
          $rule->required_feature
        ) {
          return false;
        }

        return filter_var(
          $feature->feature_value,
          FILTER_VALIDATE_BOOLEAN
        );
      });

    if (!$hasFeature) {

      throw new SubscriptionFeatureMissingException(
        sprintf(
          'Feature [%s] required.',
          $rule->required_feature
        )
      );
    }
  }
}
