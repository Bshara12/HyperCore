<?php

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Services\NotificationSubscriptionService;
use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->service = new NotificationSubscriptionService();
});

function createSubscriptionMockActor(string $type, string $id, ?string $projectId = null): NotificationActor
{
    return NotificationActor::fromArray([
        'type'       => $type,
        'id'         => $id,
        'project_id' => $projectId ?? 'project-123',
    ]);
}

/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود listForActor()
 * --------------------------------------------------------------------------
 */
it('lists subscription scoped to user when actor is a user', function () {
  $userActor = createMockActor('user', 'user-123', 'project-a');

  // اشتراك يخص المستخدم الحالي
  NotificationSubscription::create([
    'project_id' => 'project-a',
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-123',
    'topic_key' => 'billing',
    'active' => true
  ]);

  // اشتراك يخص مستخدم آخر في نفس المشروع
  NotificationSubscription::create([
    'project_id' => 'project-a',
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-555',
    'topic_key' => 'auth',
    'active' => true
  ]);

  $results = $this->service->listForActor($userActor);

  // يجب أن يعود فقط بالسجل التابع للـ user-123
  expect($results)->toHaveCount(1)
    ->and($results->first()->subscriber_id)->toBe('user-123');
});

it('lists all subscriptions inside project scope when actor is a service', function () {
  $serviceActor = createMockActor('service', 'agent-007', 'project-a');

  // سجلات متعددة لمستخدمين مختلفين داخل نفس المشروع
  NotificationSubscription::create([
    'project_id' => 'project-a',
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-111',
    'topic_key' => 'billing',
    'active' => true
  ]);
  NotificationSubscription::create([
    'project_id' => 'project-a',
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-222',
    'topic_key' => 'alerts',
    'active' => true
  ]);

  $results = $this->service->listForActor($serviceActor);

  // الخدمة (Service) يمكنها رؤية كامل المشروع وتخطي فلترة الـ User ID
  expect($results)->toHaveCount(2);
});

/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود createForActor()
 * --------------------------------------------------------------------------
 */
it('creates or updates a subscription for a user actor', function () {
  $actor = createMockActor('user', 'user-123', 'project-a');

  $data = [
    'topic_key' => 'security',
    'channel_mask' => ['mail', 'sms'],
    'filters' => ['severity' => 'high'],
    'active' => true
  ];

  $subscription = $this->service->createForActor($actor, $data);

  expect($subscription->id)->not->toBeNull()
    ->and($subscription->topic_key)->toBe('security')
    ->and($subscription->filters)->toBe(['severity' => 'high']);
});

/**
 * --------------------------------------------------------------------------
 * اختبارات التحديث والحذف وجلب السجل (find / update / delete)
 * --------------------------------------------------------------------------
 */
it('updates and deletes subscription successfully when actor owns it', function () {
  $actor = createMockActor('user', 'user-123', 'project-a');

  $subscription = NotificationSubscription::create([
    'project_id' => 'project-a',
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-123',
    'topic_key' => 'billing',
    'active' => true
  ]);

  // 1. فحص الـ Find
  $found = $this->service->findForActor($actor, $subscription->id);
  expect($found->id)->toBe($subscription->id);

  // 2. فحص الـ Update
  $updated = $this->service->updateForActor($actor, $subscription, ['active' => false]);
  expect($updated->active)->toBeFalse();

  // 3. فحص الـ Delete
  $deleted = $this->service->deleteForActor($actor, $subscription);
  expect($deleted)->toBeTrue();
});

/**
 * --------------------------------------------------------------------------
 * اختبار ميثود syncForProject()
 * --------------------------------------------------------------------------
 */
it('syncs bulk subscriptions for the entire project', function () {
  $actor = createMockActor('service', 'agent-007', 'project-a');

  $bulkData = [
    [
      'subscriber_type' => 'user',
      'subscriber_id' => 'user-777',
      'topic_key' => 'marketing',
      'active' => true
    ],
    [
      'subscriber_type' => 'user',
      'subscriber_id' => 'user-888',
      'topic_key' => 'invoices',
      'active' => false
    ]
  ];

  $results = $this->service->syncForProject($actor, $bulkData);

  expect($results)->toHaveCount(2)
    ->and($results->where('subscriber_id', 'user-777')->first()->topic_key)->toBe('marketing');
});

/**
 * --------------------------------------------------------------------------
 * اختبارات الحماية والحظر (Ownership Assertions 403)
 * --------------------------------------------------------------------------
 */
it('aborts with 403 if a user actor tries to access a subscription belonging to someone else', function () {
  $maliciousUser = createMockActor('user', 'user-attacker', 'project-a');

  // اشتراك يخص ضحية أخرى (user-victim)
  $subscription = NotificationSubscription::create([
    'project_id' => 'project-a',
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-victim',
    'topic_key' => 'private-data',
    'active' => true
  ]);

  expect(fn() => $this->service->updateForActor($maliciousUser, $subscription, ['active' => false]))
    ->toThrow(HttpException::class)
    ->and(fn() => $this->service->updateForActor($maliciousUser, $subscription, ['active' => false]))
    ->toThrow(function (HttpException $e) {
      expect($e->getStatusCode())->toBe(403)
        ->and($e->getMessage())->toBe('Forbidden.');
    });
});

it('aborts with 403 if a service actor tries to access a subscription outside its project scope', function () {
  $serviceActor = createMockActor('service', 'agent-007', 'project-a');

  // اشتراك ينتمي لمشروع آخر تماماً (project-different)
  $subscription = NotificationSubscription::create([
    'project_id' => 'project-different',
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-123',
    'topic_key' => 'logs',
    'active' => true
  ]);

  expect(fn() => $this->service->deleteForActor($serviceActor, $subscription))
    ->toThrow(HttpException::class)
    ->and(fn() => $this->service->deleteForActor($serviceActor, $subscription))
    ->toThrow(function (HttpException $e) {
      expect($e->getStatusCode())->toBe(403)
        ->and($e->getMessage())->toBe('Forbidden.');
    });
});
