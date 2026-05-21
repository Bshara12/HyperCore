<?php

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Enums\CreatorType;
use App\Domains\Notifications\Jobs\MaterializeNotificationBatchJob;
use App\Domains\Notifications\Services\NotificationBatchService;
use App\Models\Domains\Notifications\Models\NotificationBatch;
use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase; // 💡 استيراد الـ Trait المسؤول عن بناء الجداول

// 💡 تفعيل الـ Trait للتأكد من تشغيل الـ Migrations في قاعدة بيانات الـ testing
uses(RefreshDatabase::class);

beforeEach(function () {
  Bus::fake();

  $this->actor = new NotificationActor(
    type: 'service',
    id: 'service-app-1',
    permessions: ['notifications.manage'],
    projectId: 'project-xyz',
    raw: [],
    requestId: 'req-111',
    correlationId: 'corr-222',
    causationId: 'caus-333',
    ip: '127.0.0.1',
    userAgent: 'PestPHP-Test'
  );

  $this->basePayload = [
    'project_id' => 'project-xyz',
    'template_key' => 'order.placed',
    'title' => 'تم استلام طلبك',
    'body' => 'جاري تجهيز الطلب الآن.',
    'data' => ['order_id' => 1020],
    'metadata' => ['env' => 'testing'],
    'channel' => ['database', 'push'],
    'dedupe_key' => 'unique-dedupe-key-123',
    'scheduled_at' => null,
    'source' => [
      'service' => 'order-service',
      'type' => 'order.created',
    ],
    'audience' => [
      'type' => 'custom',
      'recipients' => [
        ['type' => 'user', 'id' => 'user-555']
      ]
    ]
  ];

  $this->service = new NotificationBatchService();
});

/**
 * --------------------------------------------------------------------------
 * Tests for create()
 * --------------------------------------------------------------------------
 */
it('creates a notification batch successfully and dispatches the materialization job', function () {
  $lockMock = Mockery::mock(\Illuminate\Cache\Lock::class);
  $lockMock->shouldReceive('block')
    ->once()
    ->with(5, Mockery::type('Closure'))
    ->andReturnUsing(function ($seconds, $callback) {
      return $callback();
    });

  Cache::shouldReceive('lock')
    ->once()
    ->with('notifications:batch:unique-dedupe-key-123', 30)
    ->andReturn($lockMock);

  $batch = $this->service->create($this->actor, $this->basePayload);

  expect($batch)->toBeInstanceOf(NotificationBatch::class)
    ->and($batch->project_id)->toBe('project-xyz')
    ->and($batch->created_by_type)->toBe(CreatorType::Service->value)
    ->and($batch->created_by_id)->toBe('service-app-1')
    ->and($batch->status)->toBe('queued');

  Bus::assertDispatched(MaterializeNotificationBatchJob::class, function ($job) use ($batch) {
    return $job->batchId === $batch->id;
  });
});

it('generates a fallback sha256 dedupe key lock if none is provided', function () {
  $payloadWithoutDedupe = $this->basePayload;
  unset($payloadWithoutDedupe['dedupe_key']);

  $expectedHash = hash('sha256', json_encode($payloadWithoutDedupe));

  $lockMock = Mockery::mock(\Illuminate\Cache\Lock::class);
  $lockMock->shouldReceive('block')
    ->once()
    ->with(5, Mockery::type('Closure'))
    ->andReturnUsing(fn($seconds, $callback) => $callback());

  Cache::shouldReceive('lock')
    ->once()
    ->with('notifications:batch:' . $expectedHash, 30)
    ->andReturn($lockMock);

  $batch = $this->service->create($this->actor, $payloadWithoutDedupe);

  Bus::assertDispatched(MaterializeNotificationBatchJob::class);
});

/**
 * --------------------------------------------------------------------------
 * Tests for resolveRecipients()
 * --------------------------------------------------------------------------
 */
it('resolves recipients directly from custom audience type', function () {
  $batch = (new NotificationBatch())->forceFill([
    'audience_type' => 'custom',
    'audience_query' => [
      'type' => 'custom',
      'recipients' => [
        ['type' => 'user', 'id' => '100'],
        ['type' => 'user', 'id' => '200']
      ]
    ]
  ]);

  $recipients = $this->service->resolveRecipients($batch);

  expect($recipients)->toBeArray()
    ->toHaveCount(2)
    ->and($recipients[0]['id'])->toBe('100');
});

it('resolves unique active recipients from subscription database for topic audience type', function () {
  $projectId = 'project-xyz';
  $topicKey = 'billing.invoice_created';

  // 1. المشترك الأول: نشط وسليم (يجب أن يعود)
  (new NotificationSubscription())->forceFill([
    'project_id' => $projectId,
    'topic_key' => $topicKey,
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-active-1',
    'active' => true
  ])->save();

  // 2. المشترك الثاني: غير نشط active = false لنفس الـ topic ولكن لمشترك آخر لتفادي قيود الـ Unique
  (new NotificationSubscription())->forceFill([
    'project_id' => $projectId,
    'topic_key' => $topicKey,
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-inactive-2',
    'active' => false
  ])->save();

  // 3. المشترك الثالث: يتبع مشروعاً آخر (يجب استبعاده)
  (new NotificationSubscription())->forceFill([
    'project_id' => 'other-project',
    'topic_key' => $topicKey,
    'subscriber_type' => 'user',
    'subscriber_id' => 'user-other-project',
    'active' => true
  ])->save();

  // تجهيز كائن الـ Batch الحامل للـ Topic المستهدف
  $batch = (new NotificationBatch())->forceFill([
    'project_id' => $projectId,
    'audience_type' => 'topic',
    'audience_query' => [
      'type' => 'topic',
      'topic_key' => $topicKey
    ]
  ]);

  // استدعاء دالة الحل والتحقق من النتيجة المصفاة
  $recipients = $this->service->resolveRecipients($batch);

  expect($recipients)->toBeArray()
    ->toHaveCount(1) // سيتم جلب المستخدم النشط الأول فقط واستبعاد البقية بناءً على شروط الـ Query
    ->and($recipients[0])->toBe([
      'type' => 'user',
      'id' => 'user-active-1'
    ]);
});

it('returns empty array if topic audience is missing the topic_key', function () {
  $batch = (new NotificationBatch())->forceFill([
    'project_id' => 'project-xyz',
    'audience_type' => 'topic',
    'audience_query' => [
      'type' => 'topic'
    ]
  ]);

  $recipients = $this->service->resolveRecipients($batch);

  expect($recipients)->toBeArray()->toBeEmpty();
});

it('returns empty array for unsupported or unknown audience types', function () {
  $batch = (new NotificationBatch())->forceFill([
    'audience_type' => 'invalid-audience-type',
    'audience_query' => []
  ]);

  $recipients = $this->service->resolveRecipients($batch);

  expect($recipients)->toBeArray()->toBeEmpty();
});
