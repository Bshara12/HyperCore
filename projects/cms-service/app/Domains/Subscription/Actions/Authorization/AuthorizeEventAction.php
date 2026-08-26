<?php

namespace App\Domains\Subscription\Actions\Authorization;

use App\Domains\Subscription\DTOs\Rule\AuthorizeEventDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionAccessRuleRepositoryInterface;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use App\Exceptions\FeatureMissingException;
use App\Exceptions\SubscriptionRequiredException;

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
        if (! $rule) {
            return;
        }

        // no subscription required
        if (! $rule->requires_subscription) {
            return;
        }

        $subscription = $this->subscriptionRepository
            ->findActiveSubscription(
                $dto->userId,
                $dto->projectId
            );
        if (! $subscription) {

            // required_feature is a single nullable string on the rule,
            // while the exception reports a list of feature keys.
            throw new SubscriptionRequiredException(
                requiredFeatures: array_filter([
                    $rule->required_feature,
                ])
            );
        }

        // no feature required
        if (! $rule->required_feature) {
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

        if (! $hasFeature) {

            throw new FeatureMissingException(
                $rule->required_feature
            );
        }
    }
}
