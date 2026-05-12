<?php

use App\Domains\Booking\Repositories\Interface\BookingCancellationPolicyRepositoryInterface;
use App\Models\Resource; // تأكد من استيراد الموديل
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(\App\Domains\Booking\Repositories\Eloquent\EloquentBookingCancellationPolicyRepository::class);
// استخدام RefreshDatabase يضمن إضافة البيانات ثم حذفها تلقائياً بعد كل اختبار
uses(Tests\TestCase::class, RefreshDatabase::class);

test('it returns cancellation policies for a specific resource ordered by hours_before descending', function () {
  // 1. Arrange: استخدام الفاكتوري لإنشاء الموارد
  // هذا سينشئ السجلات في قاعدة البيانات ويحترم الـ Foreign Keys
  $resource1 = Resource::factory()->create();
  $resource2 = Resource::factory()->create();

  // إدخال السياسات (بما أننا لم ننشئ فاكتوري للسياسات بعد، سنستخدم DB)
  DB::table('booking_cancellation_policies')->insert([
    [
      'resource_id' => $resource1->id,
      'hours_before' => 24,
      'refund_percentage' => 50,
      'created_at' => now(),
    ],
    [
      'resource_id' => $resource1->id,
      'hours_before' => 48,
      'refund_percentage' => 100,
      'created_at' => now(),
    ],
    [
      'resource_id' => $resource2->id,
      'hours_before' => 72,
      'refund_percentage' => 100,
      'created_at' => now(),
    ]
  ]);

  /** @var BookingCancellationPolicyRepositoryInterface $repo */
  $repo = app(BookingCancellationPolicyRepositoryInterface::class);

  // 2. Act
  $policies = $repo->getPoliciesForResource($resource1->id);

  // 3. Assert
  expect($policies)->toHaveCount(2)
    ->and($policies->first()->hours_before)->toBe(48) // الترتيب التنازلي
    ->and($policies->last()->hours_before)->toBe(24);

  // ملاحظة: بمجرد انتهاء هذا الاختبار، سيقوم Laravel بعمل Rollback لكل ما سبق
});

test('it returns empty collection if no policies exist for resource', function () {
  // Arrange
  $resource = Resource::factory()->create();
  $repo = app(BookingCancellationPolicyRepositoryInterface::class);

  // Act
  $policies = $repo->getPoliciesForResource($resource->id + 100); // رقم غير موجود

  // Assert
  expect($policies)->toBeEmpty();
});
