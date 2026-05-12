<?php

namespace App\Domains\Subscription\Actions\Authorization;

use Exception;

use App\Models\SubscriptionFeature;

use App\Domains\Subscription\DTOs\Authorization\AuthorizeContentDTO;

use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;

use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;

class AuthorizeContentAccessAction
{
    public function __construct(

        private SubscriptionRepositoryInterface
        $subscriptionRepository,

        private ContentAccessMetadataRepositoryInterface
        $contentRepository
    ) {}

    public function execute(
        AuthorizeContentDTO $dto
    ): void {

        $rule = $this->contentRepository
            ->findContentRule(
                $dto->contentType,
                $dto->contentId
            );

        /*
        |--------------------------------------------------------------------------
        | Public Content
        |--------------------------------------------------------------------------
        */

        if (!$rule) {
            return;
        }

        if (!$rule->requires_subscription) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Subscription Required
        |--------------------------------------------------------------------------
        */

        $subscription = $this->subscriptionRepository
            ->findActiveSubscription(
                $dto->userId,
                $dto->projectId
            );

        if (!$subscription) {

            throw new Exception(
                'Subscription required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Feature Required
        |--------------------------------------------------------------------------
        */

        if (!$rule->required_feature) {
            return;
        }

        $feature = $subscription
            ->plan
            ->features
            ->firstWhere(
                'feature_key',
                $rule->required_feature
            );

        if (!$feature) {

            throw new Exception(
                sprintf(
                    'Required feature missing [%s].',
                    $rule->required_feature
                )
            );
        }

        if (
            $feature->feature_type === 'boolean'
            &&
            !$feature->feature_value
        ) {

            throw new Exception(
                sprintf(
                    'Feature disabled [%s].',
                    $rule->required_feature
                )
            );
        }
    }
}