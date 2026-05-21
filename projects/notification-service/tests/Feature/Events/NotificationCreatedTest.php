<?php

use App\Events\NotificationCreated;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('broadcasts notification created event with correct payload and channels', function () {
  // 1. إنشاء كائن إشعار وهمي في قاعدة البيانات لتغذية الـ Event
  $notification = (new Notification())->forceFill([
    'id'             => 'ulid-abc-123-xyz',
    'project_id'     => 'project-omega',
    'recipient_type' => 'user',
    'recipient_id'   => 'user-777',
    'title'          => 'شحنتك في الطريق',
    'body'           => 'خرجت الشحنة الخاصة بك مع مندوب التوصيل.',
    'status'         => NotificationStatus::Queued, // أو القيمة النصية مباشرة حسب الـ Cast لديك
    'created_at'     => now(),
  ]);
  $notification->save();

  // 2. بناء كائن الـ Event
  $event = new NotificationCreated($notification);

  // 3. الفحص الأول: التحقق من القناة الخاصة (PrivateChannel) وتركيب الـ Strings الديناميكي بداخلها
  $channels = $event->broadcastOn();

  expect($channels)->toHaveCount(1)
    ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
    ->and($channels[0]->name)->toBe('private-notifications.project-omega.user.user-777');

  // 4. الفحص الثاني: التحقق من الاسم المستعار للبث (Custom Event Name)
  expect($event->broadcastAs())->toBe('notification.created');

  // 5. الفحص الثالث: التحقق من مصفوفة البيانات المرسلة وتنسيق الوقت ISOString
  $payload = $event->broadcastWith();

  expect($payload)->toBeArray()
    ->and($payload['id'])->toBe('ulid-abc-123-xyz')
    ->and($payload['title'])->toBe('شحنتك في الطريق')
    ->and($payload['body'])->toBe('خرجت الشحنة الخاصة بك مع مندوب التوصيل.')->and($payload['status'])->toBe(NotificationStatus::Queued->value)
    ->and($payload['created_at'])->toBe($notification->created_at->toISOString());
});
