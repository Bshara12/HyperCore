<?php

namespace Tests\Unit\Domains\CMS\Read\Services;

use App\Domains\CMS\Read\Services\EntryVisibilityService;
use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Mockery;

beforeEach(function () {
  $this->subscriptionRepo = Mockery::mock(SubscriptionRepositoryInterface::class);
  $this->contentRepo = Mockery::mock(ContentAccessMetadataRepositoryInterface::class);

  $this->service = new EntryVisibilityService(
    $this->subscriptionRepo,
    $this->contentRepo
  );
});

afterEach(function () {
  Mockery::close();
});

test('it returns empty array if entries are empty', function () {
  $result = $this->service->filterVisible([], 1);
  expect($result)->toBe([]);
});

test('it returns input entries if no access rules are found (Public Content)', function () {
  $entries = [['id' => 1, 'data_type_slug' => 'test', 'project_id' => 1]];

  // 1. التوقع الخاص بالـ Rules
  $this->contentRepo->shouldReceive('findManyRules')
    ->once()
    ->andReturn([]);

  // 2. إضافة التوقع الخاص بالـ Subscription (حتى لو كانت القيمة null)
  $this->subscriptionRepo->shouldReceive('findActiveSubscription')
    ->once()
    ->andReturn(null);

  $result = $this->service->filterVisible($entries, 1);
  expect($result)->toBe($entries);
});

test('it filters out private content if user has no subscription', function () {
  $entries = [['id' => 1, 'data_type_slug' => 'test', 'project_id' => 1]];

  // محاكاة قاعدة (Rule) تتطلب اشتراكاً
  $rule = (object) ['requires_subscription' => true, 'required_feature' => null];

  $this->contentRepo->shouldReceive('findManyRules')->andReturn([1 => $rule]);
  $this->subscriptionRepo->shouldReceive('findActiveSubscription')->andReturn(null);

  $result = $this->service->filterVisible($entries, 1);

  expect($result)->toBe([]); // يجب أن يتم استبعاد العنصر
});

test('it allows access if subscription feature matches', function () {
  $entries = [['id' => 1, 'data_type_slug' => 'test', 'project_id' => 1]];

  // محاكاة قاعدة تتطلب ميزة 'premium'
  $rule = (object) ['requires_subscription' => true, 'required_feature' => 'premium'];

  // محاكاة الـ Subscription والـ Plan والـ Features
  $featureMock = (object) ['feature_key' => 'premium', 'feature_value' => 'active'];
  $planMock = Mockery::mock(SubscriptionPlan::class);
  $planMock->shouldReceive('getAttribute')->with('features')->andReturn(collect([$featureMock]));

  $subscriptionMock = Mockery::mock(Subscription::class);
  $subscriptionMock->shouldReceive('getAttribute')->with('plan')->andReturn($planMock);

  $this->contentRepo->shouldReceive('findManyRules')->andReturn([1 => $rule]);
  $this->subscriptionRepo->shouldReceive('findActiveSubscription')->andReturn($subscriptionMock);

  $result = $this->service->filterVisible($entries, 1);

  expect($result)->toHaveCount(1); // تم قبول العنصر
});

test('it filters out content if subscription exists but feature is missing', function () {
  $entries = [['id' => 1, 'data_type_slug' => 'test', 'project_id' => 1]];

  // محاكاة قاعدة تتطلب ميزة 'premium'
  $rule = (object) ['requires_subscription' => true, 'required_feature' => 'premium'];

  // محاكاة Plan بها ميزة مختلفة
  $otherFeature = (object) ['feature_key' => 'basic', 'feature_value' => 'active'];
  $planMock = Mockery::mock(SubscriptionPlan::class);
  $planMock->shouldReceive('getAttribute')->with('features')->andReturn(collect([$otherFeature]));

  $subscriptionMock = Mockery::mock(Subscription::class);
  $subscriptionMock->shouldReceive('getAttribute')->with('plan')->andReturn($planMock);

  $this->contentRepo->shouldReceive('findManyRules')->andReturn([1 => $rule]);
  $this->subscriptionRepo->shouldReceive('findActiveSubscription')->andReturn($subscriptionMock);

  $result = $this->service->filterVisible($entries, 1);

  expect($result)->toBe([]); // يجب استبعاد العنصر
});

test('it returns empty array if first entry is not an array', function () {
  // يغطي الأسطر 28-30
  $result = $this->service->filterVisible(['invalid-item'], 1);
  expect($result)->toBe([]);
});

test('it does not call subscription repo if userId is null', function () {
    $entries = [['id' => 1, 'data_type_slug' => 'test', 'project_id' => 1]];

    // تأكد أن الـ ContentRepo لا يزال يتلقى الاستدعاء المطلوب
    $this->contentRepo->shouldReceive('findManyRules')
        ->once()
        ->andReturn([]);

    // استخدام never() للتأكد أن الاستدعاء لن يحدث
    $this->subscriptionRepo->shouldReceive('findActiveSubscription')
        ->never(); 

    // تأكد أنك تمرر null صراحة وليس 0 أو قيمة أخرى
    $this->service->filterVisible($entries, null);
});

test('it includes entry if rule exists but does not require subscription', function () {
    $entries = [['id' => 1, 'data_type_slug' => 'test', 'project_id' => 1]];
    $rule = (object) ['requires_subscription' => false];
    
    $this->contentRepo->shouldReceive('findManyRules')->once()->andReturn([1 => $rule]);
    
    // تمرير null هنا يمنع استدعاء findActiveSubscription في الكود
    $result = $this->service->filterVisible($entries, null);
    
    expect($result)->toHaveCount(1);
});

test('it includes entry if rule requires subscription but has no required feature', function () {
  // يغطي الأسطر 140-142
  $entries = [['id' => 1, 'data_type_slug' => 'test', 'project_id' => 1]];
  $rule = (object) [
    'requires_subscription' => true,
    'required_feature' => null // هذا يمرر الفحص في سطر 140
  ];

  $this->contentRepo->shouldReceive('findManyRules')->once()->andReturn([1 => $rule]);

  // محاكاة وجود اشتراك لكي لا يتم استبعاد العنصر عند التحقق من الاشتراك
  $this->subscriptionRepo->shouldReceive('findActiveSubscription')
    ->once()
    ->andReturn(new \App\Models\Subscription());

  $result = $this->service->filterVisible($entries, 1);
  expect($result)->toHaveCount(1);
});
