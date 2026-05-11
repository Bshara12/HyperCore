<?php

namespace App\Http\Controllers;

use App\Domains\Subscription\DTOs\Subscription\CancelSubscriptionDTO;
use App\Domains\Subscription\DTOs\Subscription\RenewSubscriptionDTO;
use App\Http\Controllers\Controller;
use App\Domains\Subscription\Services\SubscriptionService;
use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;
use App\Domains\Subscription\Requests\Subscription\CancelSubscriptionRequest;
use App\Domains\Subscription\Requests\Subscription\RenewSubscriptionRequest;
use App\Domains\Subscription\Requests\Subscription\SubscribeUserRequest;
use App\Models\Subscription;

class SubscriptionController extends Controller
{
  public function __construct(
    private SubscriptionService $service
  ) {}

  public function store(
    SubscribeUserRequest $request
  ) {

    $dto = SubscribeUserDTO::fromRequest(
      $request
    );

    $subscription = $this->service
      ->subscribe($dto);

    return response()->json([
      'data' => $subscription
    ], 201);
  }

  public function renew(
    RenewSubscriptionRequest $request,
    Subscription $subscription
  ) {

    $dto = RenewSubscriptionDTO::fromRequest(
      $request,
      $subscription
    );

    $subscription = $this->service
      ->renew($dto);

    return response()->json([
      'data' => $subscription
    ]);
  }
  public function cancel(
    CancelSubscriptionRequest $request,
    Subscription $subscription
  ) {

    $dto = CancelSubscriptionDTO::fromRequest(
      $request,
      $subscription
    );

    $subscription = $this->service
      ->cancel($dto);

    return response()->json([
      'data' => $subscription
    ]);
  }
}
