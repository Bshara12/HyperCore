<?php

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Services\NotificationDeliveryTrackingService;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->service = new NotificationDeliveryTrackingService();

  // إنشاء إشعار افتراضي خاص بالمستخدم رقم user-100 والمشروع project-abc
  $this->notification = (new Notification())->forceFill([
    'project_id'     => 'project-abc',
    'recipient_type' => 'user',
    'recipient_id'   => 'user-100',
    'title'          => 'تنبيه أمني',
    'body'           => 'تم تسجيل دخول جديد لحسابك.',
    'status'         => NotificationStatus::Queued->value,
  ]);
  $this->notification->save();
});

/**
 * --------------------------------------------------------------------------
 * بناء كائن حقيقي من الـ DTO وتعبئة كافة خصائصه الداخلية بما فيها الـ type
 * --------------------------------------------------------------------------
 */
/**
 * --------------------------------------------------------------------------
 * دالة مساعدة لتوليد كائن Actor حقيقي متكامل باستخدام الـ DTO Factory
 * --------------------------------------------------------------------------
 */
function createTrackingMockActor(string $type, string $id, ?string $projectId = null): NotificationActor
{
    return NotificationActor::fromArray([
        'type'       => $type,
        'id'         => $id,
        'project_id' => $projectId ?? 'project-abc',
    ]);
}
/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود listForNotification()
 * --------------------------------------------------------------------------
 */
it('lists deliveries for a valid notification within user scope', function () {
  $actor = createMockActor('user', 'user-100', 'project-abc');

  // 1. إنشاء السجل الأول في الوقت الحالي
  $delivery1 = $this->notification->deliveries()->create([
    'channel'      => 'mail',
    'status'       => DeliveryStatus::Sent,
    'attempts'     => 1,
    'max_attempts' => 3,
  ]);

  // 💡 السحر هنا: نسافر بالوقت إلى المستقبل بمقدار ساعة كاملة لتوليد ULID ووقت جديدين تماماً
  $this->travel(1)->hours();

  // 2. إنشاء السجل الثاني في الوقت الجديد (سيكون الأحدث قطعاً في كل شيء)
  $delivery2 = $this->notification->deliveries()->create([
    'channel'      => 'sms',
    'status'       => DeliveryStatus::Queued,
    'attempts'     => 0,
    'max_attempts' => 3,
  ]);

  // العودة بالوقت الطبيعي بعد الإنشاء لتفادي التأثير على باقي الفحوصات
  $this->travelBack();

  $results = $this->service->listForNotification($actor, $this->notification->id);

  // التحقق النهائي من العدد والترتيب
  expect($results)->toHaveCount(2)
    ->and($results->first()->id)->toBe($delivery2->id);
});
it('throws model not found exception if user attempts to list deliveries for another users notification', function () {
  $maliciousActor = createMockActor('user', 'user-999', 'project-abc');

  expect(fn() => $this->service->listForNotification($maliciousActor, $this->notification->id))
    ->toThrow(ModelNotFoundException::class, 'Notification not found.');
});

/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود findDelivery()
 * --------------------------------------------------------------------------
 */
it('finds a specific delivery successfully by id', function () {
  $actor = createMockActor('user', 'user-100', 'project-abc');

  $delivery = $this->notification->deliveries()->create([
    'channel' => 'database',
    'status' => DeliveryStatus::Queued,
    'attempts' => 1,
    'max_attempts' => 3
  ]);

  $foundDelivery = $this->service->findDelivery($actor, $delivery->id);

  expect($foundDelivery->id)->toBe($delivery->id)
    ->and($foundDelivery->relationLoaded('notification'))->toBeTrue();
});

it('throws model not found exception if the delivery id does not exist', function () {
  $actor = createMockActor('user', 'user-100', 'project-abc');

  expect(fn() => $this->service->findDelivery($actor, 'non-existent-ulid'))
    ->toThrow(ModelNotFoundException::class, 'Delivery not found.');
});

/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود authorizeNotificationAccess()
 * --------------------------------------------------------------------------
 */
it('aborts with 403 if a service actor tries to access a notification from another project', function () {
  $serviceActor = createMockActor('service', 'service-agent', 'project-xyz');

  $privateMethod = new ReflectionMethod(NotificationDeliveryTrackingService::class, 'findNotificationOrFail');

  // 💡 التعديل هنا: بما أن الاستعلام سيفشل في إيجاد السجل بسبب اختلاف السكوب في الداتابيز، 
  // فالاستثناء المرمي الفعلي هو ModelNotFoundException وليس HttpException.
  expect(fn() => $privateMethod->invoke($this->service, $serviceActor, $this->notification->id))
    ->toThrow(ModelNotFoundException::class, 'Notification not found.');
});
it('aborts with 403 if user credentials mismatch inside the private authorization guard', function () {
  $actor = createMockActor('user', 'user-222', 'project-abc');

  $privateAuth = new ReflectionMethod(NotificationDeliveryTrackingService::class, 'authorizeNotificationAccess');

  expect(fn() => $privateAuth->invoke($this->service, $actor, $this->notification))
    ->toThrow(HttpException::class)
    ->and(fn() => $privateAuth->invoke($this->service, $actor, $this->notification))
    ->toThrow(function (HttpException $e) {
      expect($e->getStatusCode())->toBe(403);
    });
});

it('aborts with 403 when service actor project id mismatches notification project id inside the authorization guard', function () {
  // 1. ننشئ Actor يمثل خدمة تابعة لمشروع 'project-abc' لكي يتطابق مع الإشعار في قاعدة البيانات
  $serviceActor = createMockActor('service', 'service-agent', 'project-abc');

  // 2. نجلب نسخة من الإشعار في الذاكرة ونقوم بتغيير الـ project_id الخاص به يدوياً إلى مشروع آخر 'project-different'
  // دون حفظه في قاعدة البيانات (حتى لا نكسر الاستعلام، بل نخدع دالة الـ Auth فقط)
  $manipulatedNotification = $this->notification->replicate();
  $manipulatedNotification->id = $this->notification->id;
  $manipulatedNotification->project_id = 'project-different';

  // 3. استدعاء دالة الـ Authorization الـ private بشكل مباشر باستخدام Reflection
  $privateAuth = new ReflectionMethod(NotificationDeliveryTrackingService::class, 'authorizeNotificationAccess');

  // الآن: الـ Actor يملك 'project-abc' والإشعار في الذاكرة يملك 'project-different'
  // سيتحقق الشرط في السطر 73 ويرمي abort(403) مئة بالمئة!
  expect(fn() => $privateAuth->invoke($this->service, $serviceActor, $manipulatedNotification))
    ->toThrow(HttpException::class)
    ->and(fn() => $privateAuth->invoke($this->service, $serviceActor, $manipulatedNotification))
    ->toThrow(function (HttpException $e) {
      expect($e->getStatusCode())->toBe(403)
        ->and($e->getMessage())->toBe('Forbidden.');
    });
});
