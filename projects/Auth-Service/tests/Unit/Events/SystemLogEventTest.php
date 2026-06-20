<?php

namespace Tests\Unit\Events;

use App\Events\SystemLogEvent;
use Illuminate\Broadcasting\PrivateChannel;

test('it correctly initializes all properties through the constructor', function () {
  // تجهيز بيانات وهمية متطابقة مع الأنواع المتوقعة في الـ Event
  $module = 'UserManagement';
  $eventType = 'update';
  $userId = 5;
  $entityType = 'App\Models\User';
  $entityId = 10;
  $oldValues = ['name' => 'Old Name'];
  $newValues = ['name' => 'New Name'];

  // إنشاء كائن الحدث وتمرير البيانات له
  $event = new SystemLogEvent(
    $module,
    $eventType,
    $userId,
    $entityType,
    $entityId,
    $oldValues,
    $newValues
  );

  // التأكيدات الصارمة لضمان حفظ البيانات بدقة داخل الـ Properties
  expect($event->module)->toBe($module);
  expect($event->eventType)->toBe($eventType);
  expect($event->userId)->toBe($userId);
  expect($event->entityType)->toBe($entityType);
  expect($event->entityId)->toBe($entityId);
  expect($event->oldValues)->toBe($oldValues);
  expect($event->newValues)->toBe($newValues);
});

test('it initializes nullable constructor properties with default null values', function () {
  // إنشاء الحدث بالمعاملات الإجبارية فقط للتأكد من أن المعاملات الاختيارية تكون null تلقائياً
  $event = new SystemLogEvent('Auth', 'login');

  expect($event->module)->toBe('Auth');
  expect($event->eventType)->toBe('login');
  expect($event->userId)->toBeNull();
  expect($event->entityType)->toBeNull();
  expect($event->entityId)->toBeNull();
  expect($event->oldValues)->toBeNull();
  expect($event->newValues)->toBeNull();
});

test('it broadcasts on the correct private channel', function () {
  $event = new SystemLogEvent('Orders', 'create');

  // استدعاء دالة البث
  $channels = $event->broadcastOn();

  // التأكيد: يجب أن تعيد مصفوفة تحتوي على عنصر واحد وهو PrivateChannel وباسم 'channel-name'
  expect($channels)->toBeArray()->toHaveCount(1);
  expect($channels[0])->toBeInstanceOf(PrivateChannel::class);

  // التأكد من أن اسم القناة بالداخل مطابق تماماً
  expect($channels[0]->name)->toBe('private-channel-name');
  // ملاحظة: لارافيل يضيف بادئة "private-" تلقائياً لاسم القناة عند استخدام PrivateChannel
});
