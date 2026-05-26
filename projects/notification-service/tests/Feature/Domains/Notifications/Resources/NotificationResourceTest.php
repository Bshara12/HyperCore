<?php

use App\Domains\Notifications\Resources\NotificationResource;
use App\Models\Domains\Notifications\Models\Notification;
use App\Domains\Notifications\Enums\NotificationStatus; // 💡 استدعاء الـ Enum الصحيح
use Carbon\Carbon;
use Illuminate\Http\Request;

beforeEach(function () {
  // تثبيت الوقت عند بداية الثانية لتجنب مشاكل الـ Microseconds أثناء الـ JSON Serialization
  $this->now = Carbon::now()->startOfSecond();

  // جلب أول حالة متاحة من الـ Enum الحقيقي لمنع الـ ValueError
  $statusEnum = null;
  if (method_exists(NotificationStatus::class, 'cases')) {
    $cases = NotificationStatus::cases();
    $statusEnum = $cases[0] ?? null;
  }

  // إنشاء كائن حقيقي فارغ وحقن الخصائص بـ forceFill
  $this->notification = (new Notification())->forceFill([
    'id' => 'notif-123',
    'project_id' => 'project-abc',
    'recipient_type' => 'user',
    'recipient_id' => 456,
    'title' => 'تنبيه جديد',
    'body' => 'محتوى الإشعار للتجربة',
    'priority' => 1,
    'topic_key' => 'billing.success',
    'data' => ['amount' => 100],
    'metadata' => ['browser' => 'Chrome'],
    'source_type' => 'system',
    'source_service' => 'payment_gateway',
    'source_id' => 'src-999',
  ]);

  // تعيين كائنات التواريخ (Carbon)
  $this->notification->read_at = $this->now;
  $this->notification->created_at = $this->now;
  $this->notification->updated_at = $this->now;

  // حقن الـ Enum الحقيقي
  $this->notification->status = $statusEnum;
});

it('transforms the notification resource attributes correctly into array', function () {
  // تجهيز الـ Request الوهمي
  $request = Request::create('/api/notifications', 'GET');

  $resource = new NotificationResource($this->notification);
  $result = $resource->toArray($request);

  // التحقق من صحة نقل البيانات والـ ISO Formatting للتواريخ والـ Nested Source Array
  expect($result)->toBeArray()
    ->and($result['id'])->toBe('notif-123')
    ->and($result['project_id'])->toBe('project-abc')
    ->and($result['recipient_type'])->toBe('user')
    ->and($result['recipient_id'])->toBe(456)
    ->and($result['title'])->toBe('تنبيه جديد')
    ->and($result['body'])->toBe('محتوى الإشعار للتجربة')
    ->and($result['status'])->toBe($this->notification->status->value)
    ->and($result['priority'])->toBe(1)
    ->and($result['topic_key'])->toBe('billing.success')
    ->and($result['data'])->toBe(['amount' => 100])
    ->and($result['metadata'])->toBe(['browser' => 'Chrome'])
    ->and($result['source'])->toBe([
      'type' => 'system',
      'service' => 'payment_gateway',
      'id' => 'src-999',
    ])
    ->and($result['read_at'])->toBe($this->notification->read_at->toISOString())
    ->and($result['created_at'])->toBe($this->notification->created_at->toISOString())
    ->and($result['updated_at'])->toBe($this->notification->updated_at->toISOString());
});

it('handles optional nullable notification dates correctly', function () {
  // تعيين التواريخ القابلة للإلغاء إلى null لاختبار الـ optional() Helper المكتوب في الـ Resource
  $this->notification->read_at = null;
  $this->notification->created_at = null;
  $this->notification->updated_at = null;

  $request = Request::create('/api/notifications', 'GET');
  $resource = new NotificationResource($this->notification);
  $result = $resource->toArray($request);

  // التأكد من عدم انهيار الكود ورجوع الحقول بـ null بأمان
  expect($result['read_at'])->toBeNull()
    ->and($result['created_at'])->toBeNull()
    ->and($result['updated_at'])->toBeNull();
});
