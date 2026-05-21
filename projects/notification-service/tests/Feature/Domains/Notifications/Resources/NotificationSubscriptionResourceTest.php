<?php

use App\Domains\Notifications\Resources\NotificationSubscriptionResource;
use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Carbon\Carbon;
use Illuminate\Http\Request;

beforeEach(function () {
  // تثبيت الوقت عند بداية الثانية لتجنب مشاكل الـ Microseconds أثناء الـ JSON Serialization
  $this->now = Carbon::now()->startOfSecond();

  // إنشاء كائن حقيقي فارغ وحقن الخصائص بـ forceFill
  $this->subscription = (new NotificationSubscription())->forceFill([
    'id' => 'sub-123',
    'project_id' => 'project-abc',
    'subscriber_type' => 'user',
    'subscriber_id' => 999,
    'topic_key' => 'orders.dispatched',
    'channel_mask' => 7, // مثال على الـ Bitmask للقنوات (SMS, Email, Push)
    'filters' => ['min_amount' => 50],
    'active' => true,
  ]);

  // تعيين التواريخ ككائنات Carbon
  $this->subscription->created_at = $this->now;
  $this->subscription->updated_at = $this->now;
});

it('transforms the notification subscription resource attributes correctly into array', function () {
  // تجهيز الـ Request الوهمي
  $request = Request::create('/api/subscriptions', 'GET');

  $resource = new NotificationSubscriptionResource($this->subscription);
  $result = $resource->toArray($request);

  // التحقق من صحة نقل البيانات والـ ISO Formatting للتواريخ
  expect($result)->toBeArray()
    ->and($result['id'])->toBe('sub-123')
    ->and($result['project_id'])->toBe('project-abc')
    ->and($result['subscriber_type'])->toBe('user')
    ->and($result['subscriber_id'])->toBe(999)
    ->and($result['topic_key'])->toBe('orders.dispatched')
    ->and($result['channel_mask'])->toBe(7)
    ->and($result['filters'])->toBe(['min_amount' => 50])
    ->and($result['active'])->toBeTrue()
    ->and($result['created_at'])->toBe($this->subscription->created_at->toISOString())
    ->and($result['updated_at'])->toBe($this->subscription->updated_at->toISOString());
});

it('handles optional nullable subscription dates correctly', function () {
  // تعيين التواريخ القابلة للإلغاء إلى null لاختبار الـ optional() Helper المكتوب في الـ Resource
  $this->subscription->created_at = null;
  $this->subscription->updated_at = null;

  $request = Request::create('/api/subscriptions', 'GET');
  $resource = new NotificationSubscriptionResource($this->subscription);
  $result = $resource->toArray($request);

  // التأكد من عدم انهيار الكود ورجوع الحقول بـ null بأمان
  expect($result['created_at'])->toBeNull()
    ->and($result['updated_at'])->toBeNull();
});
