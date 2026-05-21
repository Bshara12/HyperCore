<?php

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Services\NotificationAuthorizationService;
use App\Domains\Notifications\Services\NotificationReadService;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

beforeEach(function () {
  // 1. عمل Mock لخدمة الصلاحيات الممررة في الـ Constructor
  $this->authServiceMock = Mockery::mock(NotificationAuthorizationService::class);

  // حقن الـ Mock داخل خدمة الـ Read
  $this->service = new NotificationReadService($this->authServiceMock);

  // 2. إنشاء إشعار افتراضي غير مقروء للفحص
  $this->notification = (new Notification())->forceFill([
    'project_id'     => 'project-123',
    'recipient_type' => 'user',
    'recipient_id'   => 'user-777',
    'title'          => 'تحديث النظام',
    'body'           => 'تمت جدولة صيانة جديدة.',
    'status'         => NotificationStatus::Queued->value,
    'read_at'        => null,
  ]);
  $this->notification->save();
});

/**
 * --------------------------------------------------------------------------
 * دالة مساعدة لتوليد كائن Actor حقيقي متكامل باستخدام الـ DTO Factory
 * --------------------------------------------------------------------------
 */
function createReadMockActor(string $type, string $id, ?string $projectId = null): NotificationActor
{
  return NotificationActor::fromArray([
    'type'       => $type,
    'id'         => $id,
    'project_id' => $projectId ?? 'project-123',
  ]);
}

/**
 * --------------------------------------------------------------------------
 * اختبار ميثود paginateForActor() مع الـ Filters الـ 3 بالكامل
 * --------------------------------------------------------------------------
 */
it('paginates notifications for an actor with applied filters', function () {
  $actor = createMockActor('user', 'user-777', 'project-123');

  // توقع استدعاء ميثود التحقق من الصلاحيات وتمرير الـ Project ID لها
  $this->authServiceMock->shouldReceive('ensureCanViewAny')
    ->once()
    ->with($actor, 'project-123');

  // إنشاء إشعار إضافي يحمل تفاصيل فلترة محددة (topic_key)
  $filteredNotification = (new Notification())->forceFill([
    'project_id'     => 'project-123',
    'recipient_type' => 'user',
    'recipient_id'   => 'user-777',
    'title'          => 'فاتورة جديدة',
    'body'           => 'تم إصدار فاتورة شهر مايو.',
    'status'         => NotificationStatus::Sent->value,
    'topic_key'      => 'billing',
    'read_at'        => null,
  ]);
  $filteredNotification->save();

  // تشغيل الـ Pagination مع تفعيل الـ Filters الثلاثة لتغطية الأسطر داخل baseQuery
  $filters = [
    'status'      => NotificationStatus::Sent->value,
    'unread_only' => true,
    'topic_key'   => 'billing'
  ];

  $paginator = $this->service->paginateForActor($actor, $filters, 10);

  expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
    ->and($paginator->total())->toBe(1)
    ->and($paginator->first()->id)->toBe($filteredNotification->id);
});

/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود findForActor()
 * --------------------------------------------------------------------------
 */
it('finds a specific notification successfully for a valid actor', function () {
  $actor = createMockActor('user', 'user-777', 'project-123');

  $this->authServiceMock->shouldReceive('ensureCanView')
    ->once()
    ->with($actor, Mockery::type(Notification::class));

  $found = $this->service->findForActor($actor, $this->notification->id);

  expect($found->id)->toBe($this->notification->id);
});

it('throws model not found exception if notification does not exist or mismatches actor criteria', function () {
  $actor = createMockActor('user', 'user-777', 'project-123');

  // نمرر UUID/ULID وهمي غير موجود
  expect(fn() => $this->service->findForActor($actor, 'non-existent-id'))
    ->toThrow(ModelNotFoundException::class, 'Notification not found.');
});

/**
 * --------------------------------------------------------------------------
 * اختبارات ميثود markAsRead()
 * --------------------------------------------------------------------------
 */
it('marks a notification as read within a database transaction if it is unread', function () {
  $actor = createMockActor('user', 'user-777', 'project-123');

  $this->authServiceMock->shouldReceive('ensureCanMarkAsRead')
    ->once()
    ->with($actor, Mockery::type(Notification::class));

  expect($this->notification->read_at)->toBeNull();

  $updatedNotification = $this->service->markAsRead($actor, $this->notification->id);

  // التأكد من تعديل البيانات والـ Status بنجاح
  expect($updatedNotification->read_at)->not->toBeNull()
    ->and($updatedNotification->status)->toBe(NotificationStatus::Read);
});

it('skips modifications if the notification is already marked as read', function () {
  $actor = createMockActor('user', 'user-777', 'project-123');

  // جعل الإشعار مقروءاً مسبقاً في الداتابيز لتغطية الـ "if (is_null(...))" المعاكس
  $this->notification->forceFill([
    'read_at' => now()->subDay(),
    'status'  => NotificationStatus::Read->value
  ])->save();

  $this->authServiceMock->shouldReceive('ensureCanMarkAsRead')
    ->once()
    ->with($actor, Mockery::type(Notification::class));

  $result = $this->service->markAsRead($actor, $this->notification->id);

  // التوقيت والـ status يجب ألا يتغيرا إلى الوقت الحالي (now) بل يبقيا كما هما
  expect($result->status->value)->toBe(NotificationStatus::Read->value);
});

it('throws exception in markAsRead if notification is not found', function () {
  $actor = createMockActor('user', 'user-777', 'project-123');

  expect(fn() => $this->service->markAsRead($actor, 'invalid-id'))
    ->toThrow(ModelNotFoundException::class, 'Notification not found.');
});

/**
 * --------------------------------------------------------------------------
 * اختبار ميثود markAllAsRead()
 * --------------------------------------------------------------------------
 */
it('marks all unread notifications as read for the actor project scope', function () {
  $actor = createMockActor('user', 'user-777', 'project-123');

  $this->authServiceMock->shouldReceive('ensureCanMarkAllAsRead')
    ->once()
    ->with($actor, 'project-123');

  // إضافة إشعار آخر غير مقروء لنفس المستخدم
  (new Notification())->forceFill([
    'project_id'     => 'project-123',
    'recipient_type' => 'user',
    'recipient_id'   => 'user-777',
    'title'          => 'تنبيه 2',
    'body'           => 'محتوى 2',
    'status'         => NotificationStatus::Queued->value,
  ])->save();

  $affectedRows = $this->service->markAllAsRead($actor);

  // التحقق من تحديث السجلين معاً بنجاح في خطوة واحدة
  expect($affectedRows)->toBe(2)
    ->and(Notification::whereNull('read_at')->count())->toBe(0);
});

/**
 * --------------------------------------------------------------------------
 * اختبار ميثود unreadCount()
 * --------------------------------------------------------------------------
 */
it('returns the correct count of unread notifications for the actor', function () {
  $actor = createMockActor('user', 'user-777', 'project-123');

  $this->authServiceMock->shouldReceive('ensureCanViewAny')
    ->once()
    ->with($actor, 'project-123');

  $count = $this->service->unreadCount($actor);

  expect($count)->toBe(1);
});

afterEach(function () {
  Mockery::close();
});
