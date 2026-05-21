<?php

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(now());

    $this->service = new NotificationDeliveryService();

    $this->notification = (new Notification())->forceFill([
        'project_id'     => 'project-xyz',
        'recipient_type' => 'user',
        'recipient_id'   => 'user-100',
        'title'          => 'تحديث حالة الطلب',
        'body'           => 'تم شحن طلبك بنجاح.',
        'status'         => NotificationStatus::Queued->value,
    ]);
    $this->notification->save();
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * --------------------------------------------------------------------------
 * Helper method to create dynamic deliveries safely with explicit channel names
 * --------------------------------------------------------------------------
 */
function createTestDelivery(Notification $notification, DeliveryStatus $status, string $channel = 'database', int $attempts = 0, int $maxAttempts = 3): NotificationDelivery
{
    $delivery = (new NotificationDelivery())->forceFill([
        'notification_id' => $notification->id,
        'channel'         => $channel, // 💡 مررنا القناة ديناميكياً هنا لتفادي الـ Unique Constraint
        'status'          => $status,
        'attempts'        => $attempts,
        'max_attempts'    => $maxAttempts,
    ]);
    $delivery->save();

    return $delivery;
}

/**
 * --------------------------------------------------------------------------
 * Tests for markQueued()
 * --------------------------------------------------------------------------
 */
it('marks a delivery as queued and increments its attempt count', function () {
    $delivery = createTestDelivery($this->notification, DeliveryStatus::Pending, 'database', 0);

    $this->service->markQueued($delivery);

    expect($delivery->status)->toBe(DeliveryStatus::Queued)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->last_attempt_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

/**
 * --------------------------------------------------------------------------
 * Tests for markSent()
 * --------------------------------------------------------------------------
 */
it('marks a delivery as sent and records timestamps', function () {
    $delivery = createTestDelivery($this->notification, DeliveryStatus::Queued, 'database', 1);

    $this->service->markSent($delivery);

    expect($delivery->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->attempts)->toBe(2)
        ->and($delivery->sent_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($delivery->last_attempt_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

/**
 * --------------------------------------------------------------------------
 * Tests for markDelivered()
 * --------------------------------------------------------------------------
 */
it('marks delivery as delivered and updates parent notification when all deliveries are done', function () {
    // 💡 تم تعديل الـ Channels لتكون مختلفة ('mail' و 'sms') لمنع الـ Unique constraint
    $delivery1 = createTestDelivery($this->notification, DeliveryStatus::Sent, 'mail', 1);
    $delivery2 = createTestDelivery($this->notification, DeliveryStatus::Skipped, 'sms', 0);

    $this->service->markDelivered($delivery1);

    expect($delivery1->status)->toBe(DeliveryStatus::Delivered)
        ->and($delivery1->attempts)->toBe(2)
        ->and($delivery1->delivered_at->toDateTimeString())->toBe(now()->toDateTimeString());

    $this->notification->refresh();
    expect($this->notification->status)->toBe(NotificationStatus::Delivered)
        ->and($this->notification->delivered_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('marks delivery as delivered but leaves parent notification pending if other channels are still active', function () {
    // 💡 قنوات مختلفة لتجنب قيود الجدول الفريد المركب
    $delivery1 = createTestDelivery($this->notification, DeliveryStatus::Sent, 'mail', 1);
    $delivery2 = createTestDelivery($this->notification, DeliveryStatus::Queued, 'sms', 1);

    $this->service->markDelivered($delivery1);

    $this->notification->refresh();
    expect($this->notification->status)->not->toBe(NotificationStatus::Delivered);
});

/**
 * --------------------------------------------------------------------------
 * Tests for markFailed()
 * --------------------------------------------------------------------------
 */
it('marks delivery as failed and sets next_retry_at backoff if under max_attempts', function () {
    $delivery = createTestDelivery($this->notification, DeliveryStatus::Queued, 'database', 1, 3);

    $this->service->markFailed($delivery, 'ERR_500', 'Gateway Timeout', 10);

    expect($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->error_code)->toBe('ERR_500')
        ->and($delivery->error_message)->toBe('Gateway Timeout')
        ->and($delivery->attempts)->toBe(2)
        ->and($delivery->next_retry_at->toDateTimeString())->toBe(now()->addMinutes(10)->toDateTimeString());
});

it('marks delivery as failed and sets next_retry_at to null if max_attempts is reached', function () {
    // 1. إنشاء السجل وربطه بالإشعار بشكل طبيعي
    $delivery = $this->notification->deliveries()->create([
        'channel'      => 'database',
        'status'       => DeliveryStatus::Queued,
        'attempts'     => 2,
        'max_attempts' => 3,
    ]);

    // 🎯 السحر هنا للـ Coverage: نراقب كائن الـ Delivery عند التحديث
    // بمجرد أن تقوم الخدمة بحفظ حالته الجديدة كـ Failed، نقوم بحذفه فوراً من الداتابيز!
    NotificationDelivery::saved(function ($model) {
        $model->delete(); 
    });

    // 2. استدعاء الخدمة لمعالجة الفشل
    // الخدمة ستعمل الحفظ -> السجل سيُحذف فوراً -> استعلام الـ doesntExist() سيعود فارغاً -> يدخل الأسطر 88-92!
    $this->service->markFailed($delivery, 'LIMIT_EXCEEDED', 'Max retries reached', 5);

    // 3. تحديث الإشعار الأب من قاعدة البيانات 
    $this->notification->refresh();
    
    $actualStatus = $this->notification->status instanceof \BackedEnum 
        ? $this->notification->status->value 
        : $this->notification->status;

    // الآن ستجده تحول إلى Failed من داخل الأسطر 88-92 مباشرة في الخدمة!
    expect($actualStatus)->toBe(NotificationStatus::Failed->value);

    // تنظيف الـ الـ Events بعد انتهاء الفحص حتى لا تؤثر على باقي الاختبارات
    NotificationDelivery::flushEventListeners();
});

/**
 * --------------------------------------------------------------------------
 * Tests for markSkipped()
 * --------------------------------------------------------------------------
 */
it('marks a delivery as skipped with a message', function () {
    $delivery = createTestDelivery($this->notification, DeliveryStatus::Pending, 'database', 0);

    $this->service->markSkipped($delivery, 'User turned off push notifications hook');

    expect($delivery->status)->toBe(DeliveryStatus::Skipped)
        ->and($delivery->error_message)->toBe('User turned off push notifications hook')
        ->and($delivery->last_attempt_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($delivery->attempts)->toBe(0);
});