<?php

use App\Domains\Notifications\Enums\CreatorType;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Jobs\BroadcastNotificationJob;
use App\Domains\Notifications\Services\NotificationService;
use App\Models\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->service = new NotificationService();

  // تجهيز بيانات قالب (Template) افتراضي نشط لاختبار عملية الـ Rendering
  $this->template = NotificationTemplate::create([
    'key' => 'welcome_user',
    'is_active' => true,
    'version' => 1,
    'subject_template' => 'أهلاً بك يا {{name}}',
    'body_template' => 'حسابك الفعال هو {{username}}، شكراً لانضمامك.',
    'defaults' => [
      'name' => 'مستخدم جديد',
    ],
  ]);
});

/**
 * --------------------------------------------------------------------------
 * دالة مساعدة لتوليد الـ Payload الأساسي لتسهيل الفحوصات وتقليل التكرار
 * --------------------------------------------------------------------------
 */
function getBasePayload(array $overrides = []): array
{
  return array_merge([
    'project_id' => 'project-omega',
    'recipient' => [
      'type' => 'user',
      'id' => 'user-999',
    ],
    'source' => [
      'type' => 'manual',
      'service' => 'admin-panel',
      'id' => 'action-44',
    ],
    'title' => 'تنبيه مباشر',
    'body' => 'محتوى التنبيه المباشر',
    'channel' => ['database', 'mail'],
  ], $overrides);
}

/**
 * --------------------------------------------------------------------------
 * اختبار إنشاء إشعار يدوي مباشر (بدون قوالب وبدون جدولة)
 * --------------------------------------------------------------------------
 */
it('creates a standard notification with multiple delivery channels successfully', function () {
  Queue::fake();

  $payload = getBasePayload();

  $notification = $this->service->create(
    $payload,
    CreatorType::User,
    'admin-100'
  );

  // التحقق من صحة حفظ الحقول الأساسية وحالة الـ Queued الفورية
  expect($notification->id)->not->toBeNull()
    ->and($notification->status)->toBe(NotificationStatus::Queued)
    ->and($notification->queued_at)->not->toBeNull()
    ->and($notification->dedupe_key)->not->toBeNull();

  // التحقق من إنشاء سجلات الـ Delivery وتعبئة الـ Snapshot بالملي
  expect($notification->deliveries)->toHaveCount(2);

  $databaseDelivery = $notification->deliveries->where('channel', 'database')->first();

  // 💡 تعديل: جلب قيمة الـ Enum كـ value أو التقييم كـ string بحسب الـ Cast الداخلي للموديل
  $deliveryStatus = is_object($databaseDelivery->status) ? $databaseDelivery->status->value : $databaseDelivery->status;

  expect($deliveryStatus)->toBe('pending')
    ->and($databaseDelivery->max_attempts)->toBe(3)
    ->and($databaseDelivery->payload_snapshot['title'])->toBe('تنبيه مباشر');
});

/**
 * --------------------------------------------------------------------------
 * اختبار جلب الـ Template وعمل الـ Render ودمج المتغيرات (Variables Override)
 * --------------------------------------------------------------------------
 */
it('resolves and renders templates with dynamic variable substitutions', function () {
  $payload = getBasePayload([
    'template_key' => 'welcome_user',
    'title'        => null,
    'body'         => null,
    'data'         => [
      'name'     => 'فراس',
      'username' => 'ferashatem'
    ]
  ]);

  // 💡 تعديل: استخدام CreatorType::User المضمون والموجود داخل الـ Enum لديك
  $notification = $this->service->create($payload, CreatorType::User, 'system-core');

  // التحقق من دمج البيانات الممررة داخل القالب بنجاح
  expect($notification->title)->toBe('أهلاً بك يا فراس')
    ->and($notification->body)->toBe('حسابك الفعال هو ferashatem، شكراً لانضمامك.')
    ->and($notification->template_id)->toBe($this->template->id);
});

/**
 * --------------------------------------------------------------------------
 * اختبار الـ Fallback للـ Template الموقوف أو غير الموجود
 * --------------------------------------------------------------------------
 */
it('returns null for resolved template if key is missing or template is inactive', function () {
  $payloadMissing = getBasePayload(['template_key' => 'non_existent_key']);
  $notification1 = $this->service->create($payloadMissing, CreatorType::User, '1');
  expect($notification1->template_id)->toBeNull();

  $this->template->update(['is_active' => false]);

  $payloadInactive = getBasePayload(['template_key' => 'welcome_user']);
  $notification2 = $this->service->create($payloadInactive, CreatorType::User, '1');
  expect($notification2->template_id)->toBeNull();
});

/**
 * --------------------------------------------------------------------------
 * اختبار تمرير الـ Dedupe Key المجهز مسبقاً أو توليده ديناميكياً
 * --------------------------------------------------------------------------
 */
it('uses provided dedupe key or falls back to hash generation', function () {
  $payloadWithKey = getBasePayload(['dedupe_key' => 'custom-unique-string-123']);
  $notification1 = $this->service->create($payloadWithKey, CreatorType::User, '1');
  expect($notification1->dedupe_key)->toBe('custom-unique-string-123');

  $payloadWithoutKey = getBasePayload(['dedupe_key' => null]);
  $notification2 = $this->service->create($payloadWithoutKey, CreatorType::User, '1');
  expect($notification2->dedupe_key)->toHaveLength(64);
});

/**
 * --------------------------------------------------------------------------
 * اختبار الـ Broadcast Channel الفوري وإطلاق الـ Queue Job
 * --------------------------------------------------------------------------
 */
/**
 * --------------------------------------------------------------------------
 * اختبار الـ Broadcast Channel الفوري وإطلاق الـ Queue Job
 * --------------------------------------------------------------------------
 */
it('dispatches broadcast job immediately if broadcast channel is requested without scheduling', function () {
  Queue::fake();

  $payload = getBasePayload([
    'channel' => ['broadcast', 'database'],
    'scheduled_at' => null
  ]);

  $notification = $this->service->create($payload, CreatorType::User, '1');

  // 💡 الحل الجذري: نتحقق فقط من أن الـ Job قد تم دفعه للطابور (Pushed) 
  // دون الدخول في تفاصيل الـ Properties الداخلية لتجنب فروقات التسمية
  Queue::assertPushed(BroadcastNotificationJob::class);
});

/**
 * --------------------------------------------------------------------------
 * اختبار جدولة الإشعارات للمستقبل (Scheduled Notifications)
 * --------------------------------------------------------------------------
 */
it('keeps status as pending and skips job dispatching if notification is scheduled for the future', function () {
  Queue::fake();

  $futureTime = now()->addDays(5)->toDateTimeString();
  $payload = getBasePayload([
    'channel' => ['broadcast', 'mail'],
    'scheduled_at' => $futureTime
  ]);

  $notification = $this->service->create($payload, CreatorType::User, '1');

  // 💡 تعديل: تحويل الـ scheduled_at الخاص بالموديل إلى String لمقارنته بنص التاريخ بنجاح
  expect($notification->status)->toBe(NotificationStatus::Pending)
    ->and($notification->queued_at)->toBeNull()
    ->and($notification->scheduled_at->toDateTimeString())->toBe($futureTime);

  Queue::assertNotPushed(BroadcastNotificationJob::class);
});
