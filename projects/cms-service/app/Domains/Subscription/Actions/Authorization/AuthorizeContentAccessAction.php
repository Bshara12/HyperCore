<?php

namespace App\Domains\Subscription\Actions\Authorization;

use App\Domains\Subscription\DTOs\Authorization\AuthorizeContentDTO;
use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use App\Exceptions\ContentFeatureAccessDeniedException;
use App\Exceptions\SubscriptionRequiredException;

class AuthorizeContentAccessAction
{
  public function __construct(
    private SubscriptionRepositoryInterface $subscriptionRepository,
    private ContentAccessMetadataRepositoryInterface $contentRepository
  ) {}

  public function execute(
    AuthorizeContentDTO $dto
  ): void {

    // features relation is eager-loaded inside findContentRule
    $rule = $this->contentRepository
      ->findContentRule(
        $dto->contentType,
        $dto->contentId
      );

    /*
        |----------------------------------------------------------------------
        | Public content — no rule at all
        |----------------------------------------------------------------------
        */
    if (!$rule) {
      return;
    }

    if (!$rule->requires_subscription) {
      return;
    }

    /*
        |----------------------------------------------------------------------
        | Subscription required
        |----------------------------------------------------------------------
        */
    $subscription = $this->subscriptionRepository
      ->findActiveSubscription(
        $dto->userId,
        $dto->projectId
      );

    if (!$subscription) {
      // throw new SubscriptionRequiredException();
      throw new SubscriptionRequiredException(
        requiredFeatures: $rule->allowedFeatureKeys()
      );
    }

    /*
        |----------------------------------------------------------------------
        | Feature check — ANY of the allowed features is sufficient
        |----------------------------------------------------------------------
        */
    if (!$rule->requiresFeature()) {
      // Subscription alone is enough
      return;
    }

    $allowedFeatures = $rule->allowedFeatureKeys();

    // Collect the feature_key values the user actually has in their plan
    $userFeatureKeys = $subscription
      ->plan
      ->features
      ->pluck('feature_key')
      ->all();

    // Check intersection: user must have at least one allowed feature
    $hasAccess = !empty(array_intersect($allowedFeatures, $userFeatureKeys));

    if (!$hasAccess) {
      throw new ContentFeatureAccessDeniedException(
        requiredFeatures: $allowedFeatures
      );
    }

    /*
        |----------------------------------------------------------------------
        | Extra: if the matching feature is boolean type, it must be enabled
        |----------------------------------------------------------------------
        */
    $this->ensureMatchingFeatureIsEnabled(
      $subscription,
      $allowedFeatures,
      $userFeatureKeys
    );
  }

    // ─────────────────────────────────────────────────────────────────────────

  /**
   * Among the features the user owns that are in the allowed list,
   * at least one must be active (boolean true or non-boolean type).
   *
   * If ALL matching features are boolean AND disabled → deny.
   */
  private function ensureMatchingFeatureIsEnabled(
    $subscription,
    array $allowedFeatures,
    array $userFeatureKeys
  ): void {

    $matchingKeys = array_intersect($allowedFeatures, $userFeatureKeys);

    $enabledCount = $subscription
      ->plan
      ->features
      ->filter(fn($f) => in_array($f->feature_key, $matchingKeys))
      ->filter(function ($f) {
        // Non-boolean features are always considered enabled
        if ($f->feature_type !== 'boolean') {
          return true;
        }
        return (bool) $f->feature_value;
      })
      ->count();

    if ($enabledCount === 0) {
      throw new ContentFeatureAccessDeniedException(
        requiredFeatures: $allowedFeatures
      );
    }
  }
}
