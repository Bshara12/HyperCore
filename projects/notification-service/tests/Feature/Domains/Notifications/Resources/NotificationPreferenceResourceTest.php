<?php

use App\Domains\Notifications\Resources\NotificationPreferenceResource;
use App\Models\Domains\Notifications\Models\NotificationPreference;
use Carbon\Carbon;
use Illuminate\Http\Request;

beforeEach(function () {
  // تثبيت الوقت عند بداية الثانية لتجنب مشاكل الـ Microseconds أثناء الـ JSON Serialization
  $this->now = Carbon::now()->startOfSecond();

  // إنشاء كائن حقيقي فارغ وحقن الخصائص بـ forceFill لتفادي أي تعارض مع الـ Casts
  $this->preference = (new NotificationPreference())->forceFill([
    'id' => 'pref-123',
    'project_id' => 'project-abc',
    'recipient_type' => 'user',
    'recipient_id' => 789,
    'topic_key' => 'marketing.news',
    'channel' => 'email',
    'enabled' => true,
    'quiet_hours' => ['start' => '22:00', 'end' => '07:00'],
    'delivery_mode' => 'instant',
    'locale' => 'ar',
    'metadata' => ['device' => 'Samsung S24 Ultra'],
  ]);

  // تعيين التواريخ ككائنات Carbon
  $this->preference->mute_until = $this->now->copy()->addDays(2);
  $this->preference->created_at = $this->now;
  $this->preference->updated_at = $this->now;
});

it('transforms the notification preference resource attributes correctly into array', function () {
  // تجهيز الـ Request الوهمي
  $request = Request::create('/api/preferences', 'GET');

  $resource = new NotificationPreferenceResource($this->preference);
  $result = $resource->toArray($request);

  // التحقق من صحة نقل البيانات والـ ISO Formatting للتواريخ
  expect($result)->toBeArray()
    ->and($result['id'])->toBe('pref-123')
    ->and($result['project_id'])->toBe('project-abc')
    ->and($result['recipient_type'])->toBe('user')
    ->and($result['recipient_id'])->toBe(789)
    ->and($result['topic_key'])->toBe('marketing.news')
    ->and($result['channel'])->toBe('email')
    ->and($result['enabled'])->toBeTrue()
    ->and($result['quiet_hours'])->toBe(['start' => '22:00', 'end' => '07:00'])
    ->and($result['delivery_mode'])->toBe('instant')
    ->and($result['locale'])->toBe('ar')
    ->and($result['metadata'])->toBe(['device' => 'Samsung S24 Ultra'])
    ->and($result['mute_until'])->toBe($this->preference->mute_until->toISOString())
    ->and($result['created_at'])->toBe($this->preference->created_at->toISOString())
    ->and($result['updated_at'])->toBe($this->preference->updated_at->toISOString());
});

it('handles optional nullable preference dates correctly', function () {
  // تعيين التواريخ القابلة للإلغاء إلى null لاختبار الـ optional() Helper المكتوب في الـ Resource
  $this->preference->mute_until = null;
  $this->preference->created_at = null;
  $this->preference->updated_at = null;

  $request = Request::create('/api/preferences', 'GET');
  $resource = new NotificationPreferenceResource($this->preference);
  $result = $resource->toArray($request);

  // التأكد من عدم انهيار الكود ورجوع الحقول بـ null بأمان
  expect($result['mute_until'])->toBeNull()
    ->and($result['created_at'])->toBeNull()
    ->and($result['updated_at'])->toBeNull();
});
