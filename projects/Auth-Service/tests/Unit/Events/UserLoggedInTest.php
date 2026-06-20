<?php

namespace Tests\Unit\Events;

use App\Events\UserLoggedIn;
use Illuminate\Broadcasting\PrivateChannel;

// ─── كود الفحص والتأكيدات ──────────────────────────────────────────────────

// 1. اختبار استقبال ومعالجة البيانات في الـ Constructor
test('it correctly initializes the userId property through the constructor', function () {
  $expectedUserId = 99;

  // إنشاء كائن الحدث
  $event = new UserLoggedIn($expectedUserId);

  // التأكيد على حفظ القيمة
  expect($event->userId)->toBe($expectedUserId);
});

// 2. اختبار دالة البث (Broadcasting Channel)
test('it broadcasts on the correct private channel name', function () {
  $event = new UserLoggedIn(1);

  // استدعاء دالة البث
  $channels = $event->broadcastOn();

  // التأكيدات الصارمة لضمان صحة القناة ونوعها
  expect($channels)->toBeArray()->toHaveCount(1);
  expect($channels[0])->toBeInstanceOf(PrivateChannel::class);

  // التأكد من اسم القناة بالداخل (لارافيل يضيف بادئة private- تلقائياً للقنوات الخاصة عند الفحص)
  expect($channels[0]->name)->toBe('private-channel-name');
});
