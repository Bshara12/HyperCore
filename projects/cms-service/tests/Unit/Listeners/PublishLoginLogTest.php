<?php

namespace Tests\Unit\Listeners;

use App\Listeners\PublishLoginLog;
use App\Domains\CMS\Services\RabbitMQPublisher;
use App\Events\UserLoggedIn;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * ─── Fake RabbitMQ Publisher ───
 * كلاس صافي يعترض عملية النشر ويخزن البيانات لفحصها بدقة
 */
class FakeRabbitMQPublisher extends RabbitMQPublisher
{
  public bool $wasPublished = false;
  public array $publishedData = [];

  // تفريغ الـ Constructor لتجنب اتصالات RabbitMQ الحقيقية أثناء الفحص
  public function __construct() {}

  public function publish(array $data): void
  {
    $this->wasPublished = true;
    $this->publishedData = $data;
  }
}

// ─── كود الاختبار والتغطية الشاملة ───────────────────────────────────────

test('it publishes login log payload to rabbitmq successfully', function () {
  // 1. إنشاء الـ Fake وحقنه داخل حاوية لارافيل (Container Binding)
  $fakePublisher = new FakeRabbitMQPublisher();
  app()->instance(RabbitMQPublisher::class, $fakePublisher);

  // 2. تجهيز الحدث (Event) بالبيانات
  // ملاحظة: إذا كان الـ Constructor الخاص بـ UserLoggedIn يتوقع كائن User كامل بدلاً من معرف،
  // فاستبدل الـ 99 بـ: \App\Models\User::factory()->create() حسب إعدادات الملف لديك.
  $userId = 99;
  $event = new UserLoggedIn($userId);

  // 3. تنفيذ الـ Listener
  $listener = new PublishLoginLog();
  $listener->handle($event);

  // 4. التأكيدات الصارمة على حالة ومحتوى البيانات المرسلة (State Assertions)
  expect($fakePublisher->wasPublished)->toBeTrue();

  $payload = $fakePublisher->publishedData;

  // التأكد من بنية الـ Payload بالكامل
  expect($payload)->toHaveKeys(['event_id', 'module', 'event_type', 'user_id', 'occurred_at']);
  expect($payload['module'])->toBe('auth');
  expect($payload['event_type'])->toBe('login');
  expect($payload['user_id'])->toBe($userId);

  // التحقق من صحة الـ UUID المنشأ ديناميكياً
  expect(Str::isUuid($payload['event_id']))->toBeTrue();

  // التحقق من صحة الوقت والتاريخ الحالي
  expect($payload['occurred_at'])->toBe(now()->toDateTimeString());
});
