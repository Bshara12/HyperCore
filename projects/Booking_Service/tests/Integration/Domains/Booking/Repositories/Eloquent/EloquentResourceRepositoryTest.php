<?php

namespace Tests\Integration\Domains\Booking\Repositories\Eloquent;

use App\Domains\Booking\DTOs\AvailabilityDTO;
use App\Domains\Booking\DTOs\CancellationPolicyDTO;
use App\Domains\Booking\DTOs\ResourceDTO;
use App\Domains\Booking\Repositories\Eloquent\EloquentResourceRepository;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Models\Booking;
use App\Models\BookingCancellationPolicy;
use App\Models\Resource;
use App\Models\ResourceAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ربط الاختبار ببيئة Laravel وتنظيف قاعدة البيانات بعد كل اختبار
uses(TestCase::class, RefreshDatabase::class);

// تحديد الكلاس المستهدف لضمان دقة تقارير التغطية (Coverage)
covers(EloquentResourceRepository::class);

/**
 * دالة مساعدة لجلب الـ Repository مع Type Hinting كامل
 */
function repo(): ResourceRepositoryInterface
{
  return app(ResourceRepositoryInterface::class);
}

// ─── اختبارات الموارد (Resource) ─────────────────────────────────────────────

test('it can create a resource using DTO', function () {
  $dto = new ResourceDTO(
    name: 'Main Court',
    type: 'court',
    capacity: 4,
    status: Resource::STATUS_ACTIVE,
    projectId: 1,
    dataEntryId: 100
  );

  $resource = repo()->create($dto);

  expect($resource)->toBeInstanceOf(Resource::class)
    ->and($resource->name)->toBe('Main Court');

  expect(Resource::where('name', 'Main Court')->exists())->toBeTrue();
});

test('it can find a resource with its relations', function () {
  $resource = Resource::factory()->create();

  $found = repo()->findById($resource->id);

  expect($found->id)->toBe($resource->id)
    ->and($found->relationLoaded('activeAvailabilities'))->toBeTrue()
    ->and($found->relationLoaded('cancellationPolicies'))->toBeTrue();
});

test('it can update a resource and return fresh data', function () {
  $resource = Resource::factory()->create(['name' => 'Old Room']);
  $dto = new ResourceDTO(name: 'New Room', type: 'room');

  $updated = repo()->update($resource, $dto);

  expect($updated->name)->toBe('New Room');
  expect(Resource::where('id', $resource->id)->where('name', 'New Room')->exists())->toBeTrue();
});

test('it skips update if DTO array is empty', function () {
  $resource = Resource::factory()->create(['name' => 'Persistent Name']);

  // محاكاة DTO يعيد مصفوفة فارغة للتحديث
  $dto = \Mockery::mock(ResourceDTO::class);
  $dto->shouldReceive('toUpdateArray')->andReturn([]);

  $updated = repo()->update($resource, $dto);

  expect($updated->name)->toBe('Persistent Name');
});

test('it can delete a resource using soft delete', function () {
  $resource = Resource::factory()->create();

  repo()->delete($resource);

  expect($resource->fresh()->trashed())->toBeTrue();
});

test('it lists resources for user and maps booking status', function () {
  $projectId = 1;
  $userId = 99;

  $resource1 = Resource::factory()->create(['project_id' => $projectId, 'status' => Resource::STATUS_ACTIVE]);
  $resource2 = Resource::factory()->create(['project_id' => $projectId, 'status' => Resource::STATUS_ACTIVE]);

  // إنشاء حجز للمورد الأول لضمان تغطية منطق الـ map
  Booking::create([
    'resource_id' => $resource1->id,
    'user_id'     => $userId,
    'project_id'  => $projectId,
    'status'      => 'confirmed',
    'amount'      => 100,
    'start_at'    => now(),
    'end_at'      => now()->addHour()
  ]);

  $list = repo()->listForUser($projectId, $userId);

  expect($list)->toHaveCount(2);
  expect($list->firstWhere('id', $resource1->id)->is_booked)->toBeTrue();
  expect($list->firstWhere('id', $resource2->id)->is_booked)->toBeFalse();
});

test('it lists active resources by project', function () {
  $projectId = 5;
  Resource::factory()->count(2)->create(['project_id' => $projectId, 'status' => Resource::STATUS_ACTIVE]);
  Resource::factory()->create(['project_id' => $projectId, 'status' => 'inactive']);

  $list = repo()->listByProject($projectId);

  expect($list)->toHaveCount(2);
});

// ─── اختبارات الإتاحة (Availability) ─────────────────────────────────────────

test('it sets availabilities and overrides existing ones', function () {
  $resource = Resource::factory()->create();

  // وضع بيانات قديمة للتأكد من أن الدالة تحذفها أولاً
  ResourceAvailability::create([
    'resource_id' => $resource->id,
    'day_of_week' => 0,
    'start_time' => '00:00',
    'end_time' => '01:00',
    'slot_duration' => 60,
    'is_active' => true
  ]);

  $dtos = [
    new AvailabilityDTO(
      resourceId: $resource->id,
      dayOfWeek: 1,
      startTime: '08:00',
      endTime: '12:00',
      slotDuration: 30,
      isActive: true
    )
  ];

  repo()->setAvailabilities($resource, $dtos);

  expect($resource->availabilities()->count())->toBe(1);
  expect(ResourceAvailability::where('resource_id', $resource->id)->where('day_of_week', 1)->exists())->toBeTrue();
  expect(ResourceAvailability::where('day_of_week', 0)->exists())->toBeFalse(); // تم الحذف
});

// ─── اختبارات السياسات (Cancellation Policies) ───────────────────────────────

test('it sets cancellation policies and overrides existing ones', function () {
  $resource = Resource::factory()->create();

  // سجل قديم
  BookingCancellationPolicy::create([
    'resource_id' => $resource->id,
    'hours_before' => 48,
    'refund_percentage' => 100,
    'description' => 'Old Policy'
  ]);

  $dtos = [
    new CancellationPolicyDTO(
      resourceId: $resource->id,
      hoursBefore: 24,
      refundPercentage: 50,
      description: 'New Policy'
    )
  ];

  repo()->setPolicies($resource, $dtos);

  expect($resource->cancellationPolicies()->count())->toBe(1);
  expect(BookingCancellationPolicy::where('resource_id', $resource->id)->where('hours_before', 24)->exists())->toBeTrue();
  expect(BookingCancellationPolicy::where('hours_before', 48)->exists())->toBeFalse(); // تم الحذف
});
