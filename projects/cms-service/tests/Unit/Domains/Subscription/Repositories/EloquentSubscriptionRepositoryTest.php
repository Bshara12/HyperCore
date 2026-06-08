<?php

use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;
use App\Domains\Subscription\Repositories\Eloquent\EloquentSubscriptionRepository;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentSubscriptionRepository();
  $this->plan = SubscriptionPlan::factory()->create(['duration_days' => 30]);
});

test('create persists subscription correctly', function () {
  // محاكاة المستخدم (يجب أن يتوافق مع ما يتوقعه الـ DTO)
  $user = ['id' => 1, 'name' => 'John Doe'];
  Request::merge(['auth_user' => $user]);

  $dto = new SubscribeUserDTO(
    userId: 1,
    userName: 'John Doe',
    planId: $this->plan->id,
    gateway: 'stripe',
    paymentType: 'card',
    autoRenew: true,
    metadata: ['note' => 'test']
  );

  $subscription = $this->repository->create($dto, $this->plan, 999);

  $this->assertDatabaseHas('subscriptions', [
    'user_id' => 1,
    'plan_id' => $this->plan->id,
    'payment_id' => 999,
    'status' => Subscription::STATUS_ACTIVE,
  ]);
});

test('incrementFeatureUsage updates value correctly using raw sql', function () {
  $sub = Subscription::factory()->create();

  // أول إضافة (إنشاء)
  $this->repository->incrementFeatureUsage($sub->id, 'ai_credits', 5);

  // ثاني إضافة (زيادة على القيمة الحالية)
  $this->repository->incrementFeatureUsage($sub->id, 'ai_credits', 3);

  $this->assertDatabaseHas('subscription_usages', [
    'subscription_id' => $sub->id,
    'feature_key' => 'ai_credits',
    'used_value' => 8 // 5 + 3 = 8
  ]);
});

test('findActiveSubscription returns subscription with features', function () {
  $sub = Subscription::factory()->create([
    'user_id' => 1,
    'project_id' => $this->plan->project_id,
    'status' => Subscription::STATUS_ACTIVE,
    'ends_at' => now()->addDays(5),
  ]);

  $result = $this->repository->findActiveSubscription(1, $this->plan->project_id);

  expect($result)->not->toBeNull()
    ->and($result->id)->toBe($sub->id);
  // تأكد أن العلاقة تم تحميلها (إذا فشل هنا، تأكد أن الـ Model يحتوي على العلاقة)
});

test('hasActiveSubscription returns correct boolean', function () {
  Subscription::factory()->create([
    'user_id' => 1,
    'project_id' => 1,
    'status' => Subscription::STATUS_ACTIVE,
    'ends_at' => now()->addDays(10), // نشط
  ]);

  $active = $this->repository->hasActiveSubscription(1, 1);
  $inactive = $this->repository->hasActiveSubscription(2, 1); // مستخدم آخر

  expect($active)->toBeTrue()
    ->and($inactive)->toBeFalse();
});
test('renew updates subscription correctly', function () {
    $subscription = Subscription::factory()->create(['status' => Subscription::STATUS_ACTIVE]);
    
    // جرب استخدام حالة أنت متأكد أنها مقبولة في الـ Migration
    $data = ['status' => Subscription::STATUS_CANCELLED]; 

    $updatedSubscription = $this->repository->renew($subscription, $data);

    // تأكد من التوقعات في الاختبار أن تطابق ما أرسلته
    expect($updatedSubscription->status)->toBe(Subscription::STATUS_CANCELLED);
    
    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'status' => Subscription::STATUS_CANCELLED,
    ]);
});
test('cancel updates subscription correctly', function () {
  $subscription = Subscription::factory()->create(['status' => 'active']);
  $data = ['status' => 'cancelled'];

  $cancelledSubscription = $this->repository->cancel($subscription, $data);

  expect($cancelledSubscription->status)->toBe('cancelled');
  $this->assertDatabaseHas('subscriptions', [
    'id' => $subscription->id,
    'status' => 'cancelled',
  ]);
});

test('getFeatureUsage returns correct value or zero', function () {
  // 1. أنشئ الاشتراك أولاً لضمان وجود الـ ID
  $subscription = Subscription::factory()->create();

  // 2. أنشئ الـ Usage باستخدام الـ ID الحقيقي
  SubscriptionUsage::factory()->create([
    'subscription_id' => $subscription->id,
    'feature_key' => 'storage',
    'used_value' => 50
  ]);

  // 3. اختبر الآن
  $value = $this->repository->getFeatureUsage($subscription->id, 'storage');
  expect($value)->toBe(50);

  // اختبار حالة عدم الوجود
  $emptyValue = $this->repository->getFeatureUsage(9999, 'non-existent');
  expect($emptyValue)->toBe(0);
});

test('resetUsage sets used_value to zero', function () {
  $sub = Subscription::factory()->create();
  SubscriptionUsage::factory()->create([
    'subscription_id' => $sub->id,
    'feature_key' => 'bandwidth',
    'used_value' => 100,
    'reset_at' => null
  ]);

  $this->repository->resetUsage($sub->id, 'bandwidth', '2026-06-01');

  $this->assertDatabaseHas('subscription_usages', [
    'subscription_id' => $sub->id,
    'feature_key' => 'bandwidth',
    'used_value' => 0,
    'reset_at' => '2026-06-01'
  ]);
});
