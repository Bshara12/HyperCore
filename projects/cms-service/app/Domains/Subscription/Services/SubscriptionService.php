<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Subscription\CancelSubscriptionAction;
use App\Domains\Subscription\Actions\Subscription\RenewSubscriptionAction;
use App\Models\Subscription;
use App\Domains\Subscription\Actions\Subscription\SubscribeUserAction;
use App\Domains\Subscription\DTOs\Subscription\CancelSubscriptionDTO;
use App\Domains\Subscription\DTOs\Subscription\RenewSubscriptionDTO;
use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;

class SubscriptionService
{
  public function __construct(
    private SubscribeUserAction $subscribeUserAction,
    private RenewSubscriptionAction $renewSubscriptionAction,
    private CancelSubscriptionAction $cancelSubscriptionAction

  ) {}

  public function subscribe(
    SubscribeUserDTO $dto
  ): Subscription {

    return $this->subscribeUserAction
      ->execute($dto);
  }

  public function renew(
    RenewSubscriptionDTO $dto
  ): Subscription {

    return $this->renewSubscriptionAction
      ->execute($dto);
  }
  public function cancel(
    CancelSubscriptionDTO $dto
  ): Subscription {

    return $this->cancelSubscriptionAction
      ->execute($dto);
  }
}
