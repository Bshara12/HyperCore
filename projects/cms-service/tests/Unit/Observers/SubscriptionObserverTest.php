<?php

use App\Observers\SubscriptionObserver;
use App\Models\Subscription;
use App\Services\MessageBroker\RabbitMQPublisher;

beforeEach(function () {
  $this->publisher = Mockery::mock(RabbitMQPublisher::class);
  $this->observer = new SubscriptionObserver($this->publisher);
});

afterEach(function () {
  Mockery::close();
});

test('it publishes event when subscription is created', function () {
  $subscription = Mockery::mock(Subscription::class)->makePartial();
  $subscription->shouldReceive('loadMissing')->once()->with('plan');

  $subscription->user_id = 1;
  $subscription->id = 10;
  $subscription->plan_id = 2;
  $subscription->status = Subscription::STATUS_ACTIVE;
  $subscription->auto_renew = true;
  $subscription->starts_at = now();
  $subscription->ends_at = now()->addMonth();

  $plan = new \stdClass();
  $plan->name = 'Pro Plan';
  $plan->price = 29.99;
  $plan->currency = 'USD';
  $subscription->plan = $plan;

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.subscription.created', [
      'user_id'         => '1',
      'subscription_id' => 10,
      'plan_id'         => 2,
      'plan_name'       => 'Pro Plan',
      'plan_price'      => 29.99,
      'currency'        => 'USD',
      'status'          => Subscription::STATUS_ACTIVE,
      'starts_at'       => $subscription->starts_at->toIso8601String(),
      'ends_at'         => $subscription->ends_at->toIso8601String(),
      'auto_renew'      => true,
    ]);

  $this->observer->created($subscription);
});

test('it publishes event when subscription status changes to cancelled', function () {
  $subscription = Mockery::mock(Subscription::class)->makePartial();
  $subscription->shouldReceive('loadMissing')->once()->with('plan');
  $subscription->shouldReceive('isDirty')->with('status')->andReturn(true);

  $subscription->status = Subscription::STATUS_CANCELLED;
  $subscription->user_id = 1;
  $subscription->id = 10;
  $subscription->cancelled_at = now();
  $subscription->ends_at = now()->addDays(5);
  $subscription->metadata = ['cancel_reason' => 'Too expensive'];

  $plan = new \stdClass();
  $plan->name = 'Pro Plan';
  $subscription->plan = $plan;

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.subscription.cancelled', [
      'user_id'         => '1',
      'subscription_id' => 10,
      'plan_name'       => 'Pro Plan',
      'cancelled_at'    => $subscription->cancelled_at->toIso8601String(),
      'ends_at'         => $subscription->ends_at->toIso8601String(),
      'cancel_reason'   => 'Too expensive',
    ]);

  $this->observer->updated($subscription);
});

test('it publishes event when subscription status changes to grace_period', function () {
  $subscription = Mockery::mock(Subscription::class)->makePartial();
  $subscription->shouldReceive('loadMissing')->once()->with('plan');
  $subscription->shouldReceive('isDirty')->with('status')->andReturn(true);

  $subscription->status = Subscription::STATUS_GRACE_PERIOD;
  $subscription->user_id = 1;
  $subscription->id = 10;
  $subscription->ends_at = now()->addDays(3);

  $plan = new \stdClass();
  $plan->name = 'Pro Plan';
  $subscription->plan = $plan;

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.subscription.grace_period', [
      'user_id'         => '1',
      'subscription_id' => 10,
      'plan_name'       => 'Pro Plan',
      'ends_at'         => $subscription->ends_at->toIso8601String(),
    ]);

  $this->observer->updated($subscription);
});

test('it publishes event when subscription status changes to expired', function () {
  $subscription = Mockery::mock(Subscription::class)->makePartial();
  $subscription->shouldReceive('loadMissing')->once()->with('plan');
  $subscription->shouldReceive('isDirty')->with('status')->andReturn(true);

  $subscription->status = Subscription::STATUS_EXPIRED;
  $subscription->user_id = 1;
  $subscription->id = 10;
  $subscription->ends_at = now();

  $plan = new \stdClass();
  $plan->name = 'Pro Plan';
  $subscription->plan = $plan;

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.subscription.expired', [
      'user_id'         => '1',
      'subscription_id' => 10,
      'plan_name'       => 'Pro Plan',
      'ended_at'        => $subscription->ends_at->toIso8601String(),
    ]);

  $this->observer->updated($subscription);
});

test('it publishes event when subscription is renewed', function () {
  $subscription = Mockery::mock(Subscription::class)->makePartial();
  $subscription->shouldReceive('loadMissing')->once()->with('plan');

  // شروط الـ status غير محققة لتجاوز الشروط السابقة
  $subscription->shouldReceive('isDirty')->with('status')->andReturn(false);
  // شرط الends_at محقق
  $subscription->shouldReceive('isDirty')->with('ends_at')->andReturn(true);
  $subscription->shouldReceive('getOriginal')->with('status')->andReturn(Subscription::STATUS_GRACE_PERIOD);

  $subscription->status = Subscription::STATUS_ACTIVE;
  $subscription->user_id = 1;
  $subscription->id = 10;
  $subscription->new_ends_at = now()->addMonth();
  $subscription->ends_at = now()->addMonth();

  $plan = new \stdClass();
  $plan->name = 'Pro Plan';
  $plan->price = 29.99;
  $plan->currency = 'USD';
  $subscription->plan = $plan;

  $this->publisher->shouldReceive('publish')
    ->once()
    ->with('cms.subscription.renewed', [
      'user_id'           => '1',
      'subscription_id'   => 10,
      'plan_name'         => 'Pro Plan',
      'plan_price'        => 29.99,
      'currency'          => 'USD',
      'new_ends_at'       => $subscription->ends_at->toIso8601String(),
      'is_auto_renewed'   => true,
    ]);

  $this->observer->updated($subscription);
});
