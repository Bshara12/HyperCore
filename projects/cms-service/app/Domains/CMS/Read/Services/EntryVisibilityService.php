<?php

namespace App\Domains\CMS\Read\Services;

use Illuminate\Support\Collection;

use App\Domains\Subscription\Services\AuthorizationEngineService;
use App\Domains\Subscription\Services\ContentAuthorizationService;

use App\Domains\Subscription\DTOs\Rule\AuthorizeEventDTO;
use App\Domains\Subscription\DTOs\Authorization\AuthorizeContentDTO;
use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;

class EntryVisibilityService
{
  public function __construct(
    private SubscriptionRepositoryInterface $subscriptionRepository,
    private ContentAccessMetadataRepositoryInterface $contentRepository
  ) {}

  public function filterVisible(
    Collection|array $entries,
    ?int $userId
  ): array {
    $entries = collect($entries);

    if ($entries->isEmpty()) {
      return [];
    }

    $firstEntry = $entries->first();

    if (!is_array($firstEntry)) {
      return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Shared Metadata
    |--------------------------------------------------------------------------
    */

    $contentType = $firstEntry['data_type_slug'];

    $projectId = $firstEntry['project_id'];

    /*
    |--------------------------------------------------------------------------
    | Preload Rules
    |--------------------------------------------------------------------------
    */

    $contentIds = $entries
      ->pluck('id')
      ->toArray();

    $rules = $this->contentRepository
      ->findManyRules(
        $contentType,
        $contentIds
      );

    /*
    |--------------------------------------------------------------------------
    | Preload Subscription
    |--------------------------------------------------------------------------
    */

    $subscription = $userId
      ? $this->subscriptionRepository
      ->findActiveSubscription(
        $userId,
        $projectId
      )
      : null;

    /*
    |--------------------------------------------------------------------------
    | Feature Keys
    |--------------------------------------------------------------------------
    */

    $features = $subscription
      ? $subscription->plan->features
      ->pluck('feature_value', 'feature_key')
      : collect();

    /*
    |--------------------------------------------------------------------------
    | Filter
    |--------------------------------------------------------------------------
    */

    return $entries

      ->filter(function (
        array $entry
      ) use (
        $rules,
        $subscription,
        $features
      ) {

        $rule = $rules[$entry['id']] ?? null;

        /*
            |--------------------------------------------------------------------------
            | Public
            |--------------------------------------------------------------------------
            */

        if (!$rule) {
          return true;
        }

        if (!$rule->requires_subscription) {
          return true;
        }

        /*
            |--------------------------------------------------------------------------
            | Guest
            |--------------------------------------------------------------------------
            */

        if (!$subscription) {
          return false;
        }

        /*
            |--------------------------------------------------------------------------
            | No Feature Required
            |--------------------------------------------------------------------------
            */

        if (!$rule->required_feature) {
          return true;
        }

        /*
            |--------------------------------------------------------------------------
            | Feature Check
            |--------------------------------------------------------------------------
            */

        return $features->has(
          $rule->required_feature
        );
      })

      ->values()

      ->toArray();
  }
}
