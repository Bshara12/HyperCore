<?php

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Enums\CreatorType;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\SourceType;
use App\Domains\Notifications\Jobs\DispatchNotificationDeliveryJob;
use App\Domains\Notifications\Services\NotificationAuthorizationService;
use App\Domains\Notifications\Services\NotificationPreferenceService;
use App\Domains\Notifications\Services\NotificationWriteService; // 🎯 السطر المضاف هنا
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->authServiceMock = Mockery::mock(NotificationAuthorizationService::class);
  $this->preferenceServiceMock = Mockery::mock(NotificationPreferenceService::class);

  $this->service = new NotificationWriteService(
    $this->authServiceMock,
    $this->preferenceServiceMock
  );

  $this->template = NotificationTemplate::create([
    'key' => 'welcome_deal',
    'is_active' => true,
    'version' => 1,
    'subject_template' => 'أهلاً بك يا {{name}}',
    'body_template' => 'كود الخصم الخاص بك هو {{code}}.',
    'defaults' => [
      'name' => 'عميلنا العزيز',
      'code' => 'WELCOME10'
    ]
  ]);
});

/**
 * --------------------------------------------------------------------------
 * دالة مساعدة لتوليد كائن Actor حقيقي متكامل باستخدام الـ DTO Factory
 * --------------------------------------------------------------------------
 */
function createFullMockActor(string $type, string $id): NotificationActor
{
  return NotificationActor::fromArray([
    'type'           => $type,
    'id'             => $id,
    'project_id'     => 'project-omega',
    'permessions'    => [],
    'raw'            => [],
    'request_id'     => 'req-333',
    'correlation_id' => 'corr-111',
    'causation_id'   => 'caus-222',
    'ip'             => '127.0.0.1',
    'user_agent'     => 'PestTesting'
  ]);
}

function getBaseWritePayload(array $overrides = []): array
{
  return array_merge([
    'project_id' => 'project-omega',
    'recipient' => ['type' => 'user', 'id' => 'user-999'],
    'source' => ['type' => SourceType::DomainEvent->value, 'service' => 'orders', 'id' => 'order-50'],
    'title' => 'تنبيه مباشر',
    'body' => 'محتوى يدوي',
    'topic_key' => 'billing',
    'channel' => ['database', 'mail'],
    'dedupe_key' => 'unique-action-lock-key-xyz'
  ], $overrides);
}

/**
 * --------------------------------------------------------------------------
 * اختبار سيناريو الإنشاء الناجح الفوري لإشعار (User Actor)
 * --------------------------------------------------------------------------
 */
it('creates a notification with database and custom channel successfully for a user actor', function () {
  Bus::fake();
  $actor = createFullMockActor('user', 'user-100');

  $this->authServiceMock->shouldReceive('ensureCanCreate')->once()->with($actor);

  $this->preferenceServiceMock->shouldReceive('isChannelEnabled')
    ->twice()
    ->andReturn(true);

  $payload = getBaseWritePayload();

  $notification = $this->service->create($actor, $payload);

  expect($notification->id)->not->toBeNull()
    ->and($notification->status)->toBe(NotificationStatus::Queued)
    ->and($notification->created_by_type)->toBe(CreatorType::User->value)
    ->and($notification->correlation_id)->toBe('corr-111');

  expect($notification->deliveries)->toHaveCount(2);

  $dbDelivery = $notification->deliveries->where('channel', 'database')->first();
  // 💡 التعديل: جلب الـ value أو تحويل الـ status لنص للتوافق مع الـ Backend Enum
  $dbStatus = is_object($dbDelivery->status) ? $dbDelivery->status->value : $dbDelivery->status;
  expect($dbStatus)->toBe('delivered')
    ->and($dbDelivery->delivered_at)->not->toBeNull();

  $mailDelivery = $notification->deliveries->where('channel', 'mail')->first();
  $mailStatus = is_object($mailDelivery->status) ? $mailDelivery->status->value : $mailDelivery->status;
  expect($mailStatus)->toBe('pending');

  Bus::assertDispatched(DispatchNotificationDeliveryJob::class, function ($job) use ($mailDelivery) {
    // فحص ديناميكي لاسم الخاصية الممررة في كود الـ Job لديك لمنع اعتراض المترجم
    $targetId = $job->deliveryId ?? $job->id ?? null;
    return $targetId === $mailDelivery->id;
  });
});

/**
 * --------------------------------------------------------------------------
 * اختبار سيناريو الإنشاء لـ Service Actor مع التحقق من الـ System Auth
 * --------------------------------------------------------------------------
 */
it('authorizes system production when actor is a service', function () {
  Bus::fake();
  $actor = createFullMockActor('service', 'microservice-auth');

  $this->authServiceMock->shouldReceive('ensureCanCreateSystem')->once()->with($actor);
  $this->preferenceServiceMock->shouldReceive('isChannelEnabled')->andReturn(true);

  $payload = getBaseWritePayload(['dedupe_key' => 'service-lock-key']);

  $notification = $this->service->create($actor, $payload);

  expect($notification->created_by_type)->toBe(CreatorType::Service->value)
    ->and($notification->created_by_id)->toBe('microservice-auth');
});

/**
 * --------------------------------------------------------------------------
 * اختبار منع التكرار وإرجاع السجل القديم (Idempotency / findDuplicate)
 * --------------------------------------------------------------------------
 */
it('returns existing notification if a duplicate dedupe key is captured within the lock', function () {
  $actor = createFullMockActor('user', 'user-100');
  $this->authServiceMock->shouldReceive('ensureCanCreate')->twice();
  $this->preferenceServiceMock->shouldReceive('isChannelEnabled')->andReturn(true);

  $payload = getBaseWritePayload(['dedupe_key' => 'strictly-idempotent-key']);

  $firstResult = $this->service->create($actor, $payload);
  $secondResult = $this->service->create($actor, $payload);

  expect($secondResult->id)->toBe($firstResult->id)
    ->and(Notification::count())->toBe(1);
});

/**
 * --------------------------------------------------------------------------
 * اختبار حل القالب والدمج مع القيم الافتراضية والتخصيص (resolve & render)
 * --------------------------------------------------------------------------
 */
it('resolves active template, renders elements and falls back to payload title if provided', function () {
  $actor = createFullMockActor('user', 'user-100');
  $this->authServiceMock->shouldReceive('ensureCanCreate');
  $this->preferenceServiceMock->shouldReceive('isChannelEnabled')->andReturn(true);

  $payload = getBaseWritePayload([
    'dedupe_key' => null,
    'template_key' => 'welcome_deal',
    'title' => null,
    'body' => null,
    'data' => [
      'name' => 'فراس'
    ]
  ]);

  $notification = $this->service->create($actor, $payload);

  expect($notification->title)->toBe('أهلاً بك يا فراس')
    ->and($notification->body)->toBe('كود الخصم الخاص بك هو WELCOME10.');

  // 🎯 التعديل: التأكد أن القيمة تم حفظها كـ null في قاعدة البيانات بناءً على منطق الخدمة لديك
  expect($notification->dedupe_key)->toBeNull();
});

/**
 * --------------------------------------------------------------------------
 * اختبار جدولة الإشعارات للمستقبل (Scheduled Notifications)
 * --------------------------------------------------------------------------
 */
it('marks status as pending and skips delivery dispatching if scheduled in future', function () {
  Bus::fake();
  $actor = createFullMockActor('user', 'user-100');

  $this->authServiceMock->shouldReceive('ensureCanCreate');
  $this->preferenceServiceMock->shouldReceive('isChannelEnabled')->andReturn(true);

  $payload = getBaseWritePayload([
    'dedupe_key' => 'schedule-lock',
    'scheduled_at' => now()->addHours(12)->toDateTimeString()
  ]);

  $notification = $this->service->create($actor, $payload);

  expect($notification->status)->toBe(NotificationStatus::Pending);
  Bus::assertNotDispatched(DispatchNotificationDeliveryJob::class);
});

/**
 * --------------------------------------------------------------------------
 * اختبار تجاهل القنوات المغلقة بناءً على تفضيلات العميل (isChannelEnabled = false)
 * --------------------------------------------------------------------------
 */
it('skips delivery record creation if the channel is disabled in preferences', function () {
  Bus::fake();
  $actor = createFullMockActor('user', 'user-100');

  $this->authServiceMock->shouldReceive('ensureCanCreate');

  $this->preferenceServiceMock->shouldReceive('isChannelEnabled')
    ->with($actor, 'billing', 'database')
    ->andReturn(true);

  $this->preferenceServiceMock->shouldReceive('isChannelEnabled')
    ->with($actor, 'billing', 'mail')
    ->andReturn(false);

  $payload = getBaseWritePayload(['dedupe_key' => 'pref-skip-lock']);

  $notification = $this->service->create($actor, $payload);

  expect($notification->deliveries)->toHaveCount(1)
    ->and($notification->deliveries->first()->channel)->toBe('database');
});

afterEach(function () {
  Mockery::close();
});
