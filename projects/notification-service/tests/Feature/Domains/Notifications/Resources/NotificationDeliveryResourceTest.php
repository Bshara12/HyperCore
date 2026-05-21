<?php

use App\Domains\Notifications\Resources\NotificationDeliveryResource;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use App\Domains\Notifications\Enums\DeliveryStatus; // 💡 استدعاء الـ Enum الصحيح المكتشف
use Carbon\Carbon;
use Illuminate\Http\Request;

beforeEach(function () {
  // تثبيت الوقت عند بداية الثانية لتجنب مشاكل الـ Microseconds أثناء الـ JSON Serialization
  $this->now = Carbon::now()->startOfSecond();

  // جلب أول حالة متاحة من الـ Enum الحقيقي المكتشف لمنع الـ ValueError
  $statusEnum = null;
  if (method_exists(DeliveryStatus::class, 'cases')) {
    $cases = DeliveryStatus::cases();
    $statusEnum = $cases[0] ?? null;
  }

  // إنشاء كائن حقيقي فارغ وحقن الخصائص بـ forceFill
  $this->delivery = (new NotificationDelivery())->forceFill([
    'id' => 'delivery-123',
    'notification_id' => 'notif-456',
    'channel' => 'sms',
    'provider' => 'twilio',
    'attempts' => 2,
    'max_attempts' => 5,
    'provider_message_id' => 'msg_twilio_789',
    'error_code' => '400',
    'error_message' => 'Invalid phone number format',
  ]);

  // تعيين كائات التواريخ (Carbon)
  $this->delivery->last_attempt_at = $this->now;
  $this->delivery->next_retry_at = $this->now->copy()->addMinutes(15);
  $this->delivery->sent_at = $this->now;
  $this->delivery->delivered_at = $this->now;
  $this->delivery->created_at = $this->now;
  $this->delivery->updated_at = $this->now;

  // حقن الـ Enum الحقيقي
  $this->delivery->status = $statusEnum;
});

it('transforms the delivery resource attributes correctly into array', function () {
  $request = Request::create('/api/deliveries', 'GET');

  $resource = new NotificationDeliveryResource($this->delivery);
  $result = $resource->toArray($request);

  expect($result)->toBeArray()
    ->and($result['id'])->toBe('delivery-123')
    ->and($result['notification_id'])->toBe('notif-456')
    ->and($result['channel'])->toBe('sms')
    ->and($result['provider'])->toBe('twilio')
    // 💡 المقارنة بالقيمة الفعلية للـ Enum المستخرج ديناميكياً لتجنب اختلاف النص
    ->and($result['status'])->toBe($this->delivery->status->value)
    ->and($result['attempts'])->toBe(2)
    ->and($result['max_attempts'])->toBe(5)
    ->and($result['provider_message_id'])->toBe('msg_twilio_789')
    ->and($result['error_code'])->toBe('400')
    ->and($result['error_message'])->toBe('Invalid phone number format')
    ->and($result['last_attempt_at'])->toBe($this->delivery->last_attempt_at->toISOString())
    ->and($result['next_retry_at'])->toBe($this->delivery->next_retry_at->toISOString())
    ->and($result['sent_at'])->toBe($this->delivery->sent_at->toISOString())
    ->and($result['delivered_at'])->toBe($this->delivery->delivered_at->toISOString())
    ->and($result['created_at'])->toBe($this->delivery->created_at->toISOString())
    ->and($result['updated_at'])->toBe($this->delivery->updated_at->toISOString());
});

it('handles optional nullable delivery dates correctly', function () {
  $this->delivery->last_attempt_at = null;
  $this->delivery->next_retry_at = null;
  $this->delivery->sent_at = null;
  $this->delivery->delivered_at = null;
  $this->delivery->created_at = null;
  $this->delivery->updated_at = null;

  $request = Request::create('/api/deliveries', 'GET');
  $resource = new NotificationDeliveryResource($this->delivery);
  $result = $resource->toArray($request);

  expect($result['last_attempt_at'])->toBeNull()
    ->and($result['next_retry_at'])->toBeNull()
    ->and($result['sent_at'])->toBeNull()
    ->and($result['delivered_at'])->toBeNull()
    ->and($result['created_at'])->toBeNull()
    ->and($result['updated_at'])->toBeNull();
});
