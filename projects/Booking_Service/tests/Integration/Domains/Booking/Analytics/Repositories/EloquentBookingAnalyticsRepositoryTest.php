<?php

namespace Tests\Integration\Domains\Booking\Analytics\Repositories;

use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\Booking\Analytics\Repositories\EloquentBookingAnalyticsRepository;
use App\Models\Booking;
use App\Models\Resource;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
  $db = DB::connection()->getPdo();

  // 1. محاكاة DATE_FORMAT
  $db->sqliteCreateFunction('DATE_FORMAT', function ($date, $format) {
    if (!$date) return null;
    $phpFormat = str_replace(
      ['%Y', '%m', '%d', '%H', '%i', '%x', '%v', '%W'],
      ['Y', 'm', 'd', 'H', 'i', 'Y', 'W', 'l'],
      $format
    );
    return date($phpFormat, strtotime($date));
  });

  // 2. حل مشكلة "No such column" للوحدات الزمنية
  $db->sqliteCreateFunction('MINUTE', fn() => 'MINUTE');
  $db->sqliteCreateFunction('HOUR', fn() => 'HOUR');

  // 3. محاكاة TIMESTAMPDIFF
  $db->sqliteCreateFunction('TIMESTAMPDIFF', function ($unit, $start, $end) {
    if (!$start || !$end) return 0;
    $diff = strtotime($end) - strtotime($start);
    $unit = trim(strtoupper($unit), "'\""); // تنظيف الوحدات من الاقتباسات
    return match ($unit) {
      'HOUR' => floor($diff / 3600),
      'MINUTE' => floor($diff / 60),
      'SECOND' => $diff,
      default => $diff,
    };
  });

  // 4. محاكاة دالة HOUR(column)
  $db->sqliteCreateFunction('HOUR', function ($date) {
    return (int) date('H', strtotime($date));
  });

  // --- الإضافات الجديدة لـ getPeakTimes ---

  // 5. محاكاة DAYOFWEEK (MySQL تعيد 1 للأحد و 7 للسبت)
  $db->sqliteCreateFunction('DAYOFWEEK', function ($date) {
    if (!$date) return null;
    return (int) date('w', strtotime($date)) + 1;
  });

  // 6. محاكاة DAYNAME (تعيد اسم اليوم كاملاً مثل Friday)
  $db->sqliteCreateFunction('DAYNAME', function ($date) {
    if (!$date) return null;
    return date('l', strtotime($date));
  });
});
/**
 * دالة مساعدة لإنشاء الـ DTO
 */
function createFilterDTO($projectId = 1): AnalyticsFilterDTO
{
  return new AnalyticsFilterDTO(
    from: now()->subDays(7)->format('Y-m-d'),
    to: now()->format('Y-m-d'),
    period: 'daily',
    projectId: $projectId,
    limit: 10
  );
}

test('getBookingTrend aggregates data correctly by daily period', function () {
  $projectId = 1;
  $repo = new EloquentBookingAnalyticsRepository();

  // 1. إنشاء حجوزات في أيام مختلفة
  // اليوم الأول: حجزين
  Booking::factory()->create([
    'project_id' => $projectId,
    'amount' => 100,
    'status' => 'confirmed',
    'created_at' => '2026-05-01 10:00:00'
  ]);
  Booking::factory()->create([
    'project_id' => $projectId,
    'amount' => 200,
    'status' => 'completed',
    'created_at' => '2026-05-01 15:00:00'
  ]);

  // اليوم الثاني: حجز واحد
  Booking::factory()->create([
    'project_id' => $projectId,
    'amount' => 150,
    'status' => 'confirmed',
    'created_at' => '2026-05-02 12:00:00'
  ]);

  // حجز ملغى (يجب تجاهله بناءً على الكود)
  Booking::factory()->create([
    'project_id' => $projectId,
    'amount' => 500,
    'status' => 'cancelled',
    'created_at' => '2026-05-01 08:00:00'
  ]);

  $dto = new AnalyticsFilterDTO('2026-05-01', '2026-05-05', 'daily', $projectId, 10);

  // Act
  $results = $repo->getBookingTrend($dto);

  // Assert
  expect($results['data'])->toHaveCount(2); // يومين فقط

  // اليوم الأول (2026-05-01)
  $day1 = collect($results['data'])->firstWhere('label', '2026-05-01');
  expect($day1['bookings_count'])->toBe(2);
  expect($day1['revenue'])->toBe(300.0); // 100 + 200
  expect($day1['avg_value'])->toBe(150.0);

  // اليوم الثاني (2026-05-02)
  $day2 = collect($results['data'])->firstWhere('label', '2026-05-02');
  expect($day2['bookings_count'])->toBe(1);
  expect($day2['revenue'])->toBe(150.0);
});

test('getBookingTrend returns empty data when no bookings exist', function () {
  $projectId = 1;
  $repo = new EloquentBookingAnalyticsRepository();
  $dto = new AnalyticsFilterDTO('2026-01-01', '2026-01-31', 'monthly', $projectId, 10);

  $results = $repo->getBookingTrend($dto);

  expect($results['data'])->toBeArray()->toBeEmpty();
  expect($results['period'])->toBe('monthly');
});
test('getBookingTrend respects date range boundaries', function () {
  $projectId = 1;
  $repo = new EloquentBookingAnalyticsRepository();

  // إنشاء مورد
  $resource = Resource::factory()->create(['project_id' => $projectId]);

  // 1. حجز خارج النطاق (قبل شهر أبريل)
  // نغير وقت النظام وهمياً إلى مارس
  Carbon::setTestNow('2026-03-31 23:59:59');
  Booking::factory()->create([
    'project_id' => $projectId,
    'resource_id' => $resource->id,
    'status' => 'confirmed', // تأكد أن الحالة ليست cancelled لأن استعلامك يتجاهلها
    'deleted_at' => null
  ]);

  // 2. حجز داخل النطاق (بداية أبريل)
  // نغير وقت النظام وهمياً إلى أبريل
  Carbon::setTestNow('2026-04-01 10:00:00');
  Booking::factory()->create([
    'project_id' => $projectId,
    'resource_id' => $resource->id,
    'status' => 'confirmed',
    'deleted_at' => null
  ]);

  // إعادة وقت النظام للوقت الحالي لتجنب مشاكل في باقي الاختبارات
  Carbon::setTestNow();

  // تنفيذ الاستعلام لشهر أبريل
  $dto = new AnalyticsFilterDTO('2026-04-01', '2026-04-30', 'daily', $projectId, 10);
  $results = $repo->getBookingTrend($dto);

  // الآن يجب أن يجد الحجز الذي أُنشئ في "وقت النظام" أبريل
  expect($results['data'])->toHaveCount(1);
});

test('getOverview returns correct structure and calculations with data', function () {
  $projectId = 1;
  $repo = new EloquentBookingAnalyticsRepository();

  // 1. تجهيز بيانات الموارد (Resources)
  Resource::factory()->create(['project_id' => $projectId, 'status' => 'active', 'payment_type' => 'paid']);
  Resource::factory()->create(['project_id' => $projectId, 'status' => 'active', 'payment_type' => 'free']);
  Resource::factory()->create(['project_id' => $projectId, 'status' => 'inactive', 'payment_type' => 'paid']);

  // 2. تجهيز بيانات الحجوزات (Bookings) بوضعيات مختلفة
  // حجز مكتمل
  Booking::factory()->create([
    'project_id' => $projectId,
    'status' => 'completed',
    'amount' => 100,
    'created_at' => now()->subDay()
  ]);

  // حجز ملغى مع استرداد
  Booking::factory()->create([
    'project_id' => $projectId,
    'status' => 'cancelled',
    'amount' => 50,
    'refund_amount' => 20,
    'created_at' => now()->subDay()
  ]);

  // حجز عدم حضور (No-Show)
  Booking::factory()->create([
    'project_id' => $projectId,
    'status' => 'no_show',
    'amount' => 80,
    'created_at' => now()->subDay()
  ]);

  // Act (تنفيذ التابع)
  $results = $repo->getOverview(createFilterDTO($projectId));

  // Assert (التأكد من صحة الحسابات)
  expect($results['bookings']['total'])->toBe(3);
  expect($results['bookings']['total_revenue'])->toBe(230.0); // 100 + 50 + 80
  expect($results['bookings']['total_refunded'])->toBe(20.0);
  expect($results['bookings']['avg_booking_value'])->toBe(76.67); // 230 / 3

  // التأكد من النسب المئوية
  expect($results['bookings']['cancellation_rate'])->toBe(33.33); // 1 من أصل 3
  expect($results['bookings']['no_show_rate'])->toBe(33.33);
  expect($results['bookings']['completion_rate'])->toBe(33.33);

  // التأكد من الموارد
  expect($results['resources']['total'])->toBe(3);
  expect($results['resources']['active'])->toBe(2);
  expect($results['resources']['paid_resources'])->toBe(2);
});

test('getOverview handles zero results correctly', function () {
  $projectId = 99; // مشروع فارغ
  $repo = new EloquentBookingAnalyticsRepository();

  $results = $repo->getOverview(createFilterDTO($projectId));

  // Assert الحالات الصفرية (لتغطية شروط $total > 0)
  expect($results['bookings']['total'])->toBe(0);
  expect($results['bookings']['cancellation_rate'])->toBe(0);
  expect($results['bookings']['no_show_rate'])->toBe(0);
  expect($results['bookings']['completion_rate'])->toBe(0);
  expect($results['resources']['total'])->toBe(0);
});

test('getOverview excludes deleted data', function () {
  $projectId = 1;
  $repo = new EloquentBookingAnalyticsRepository();

  // إنشاء حجز ومورد ثم حذفهما (Soft Delete)
  $booking = Booking::factory()->create(['project_id' => $projectId, 'status' => 'completed']);
  $resource = Resource::factory()->create(['project_id' => $projectId]);

  $booking->delete();
  $resource->delete();

  $results = $repo->getOverview(createFilterDTO($projectId));

  expect($results['bookings']['total'])->toBe(0);
  expect($results['resources']['total'])->toBe(0);
});

test('getResourcePerformance calculates occupancy and metrics correctly', function () {
  $projectId = 1;
  $repo = new EloquentBookingAnalyticsRepository();

  // 1. إنشاء مورد (Resource) بسعة 1 لسهولة الحساب
  $resource = Resource::factory()->create([
    'project_id' => $projectId,
    'capacity' => 1,
    'price' => 100,
    'name' => 'Meeting Room'
  ]);

  // 2. ضبط الإتاحة (Availability): 10 ساعات يومياً (من 8 صباحاً لـ 6 مساءً)
  DB::table('resource_availabilities')->insert([
    'resource_id' => $resource->id,
    'day_of_week' => date('w'), // اليوم الحالي
    'start_time' => '08:00:00',
    'end_time' => '18:00:00',
    'is_active' => true,
  ]);

  // 3. إنشاء حجز مكتمل لمدة ساعتين (من 08:00 لـ 10:00)
  // لاحظ: occupancy يجب أن تكون (2 ساعة محجوزة / 10 ساعات متاحة) * 100 = 20%
  Booking::factory()->create([
    'project_id' => $projectId,
    'resource_id' => $resource->id,
    'status' => 'completed',
    'amount' => 200,
    'start_at' => now()->format('Y-m-d 08:00:00'),
    'end_at' => now()->format('Y-m-d 10:00:00'),
    'created_at' => now()
  ]);

  // حجز ملغى (يجب ألا يحسب في الساعات المحجوزة ولا في الإيرادات بناءً على كودك)
  Booking::factory()->create([
    'project_id' => $projectId,
    'resource_id' => $resource->id,
    'status' => 'cancelled',
    'amount' => 100,
    'start_at' => now()->format('Y-m-d 11:00:00'),
    'end_at' => now()->format('Y-m-d 12:00:00'),
    'created_at' => now()
  ]);

  $dto = new AnalyticsFilterDTO(now()->format('Y-m-d'), now()->format('Y-m-d'), 'daily', $projectId, 10);

  // Act
  $results = $repo->getResourcePerformance($dto);

  // Assert
  expect($results['resources'])->toHaveCount(1);
  $data = $results['resources'][0];

  expect($data['resource_id'])->toBe($resource->id);
  expect($data['total_bookings'])->toBe(2);
  expect($data['completed'])->toBe(1);
  expect($data['cancelled'])->toBe(1);

  // حسابات الساعات (Occupancy)
  expect($data['total_available_hours'])->toBe(10.0); // 10 ساعات * يوم واحد * سعة 1
  expect($data['total_booked_hours'])->toBe(2.0);    // الحجز المكتمل فقط
  expect($data['occupancy_rate'])->toBe(20.0);       // (2/10)*100

  // حسابات متوسط الوقت (2 ساعة = 120 دقيقة)
  expect($data['avg_duration_minutes'])->toBe(120.0);

  // حسابات المال
  expect($data['total_revenue'])->toBe(200.0); // الحجز المكتمل فقط
});

test('getResourcePerformance handles resources with no availabilities', function () {
  $projectId = 1;
  $resource = Resource::factory()->create(['project_id' => $projectId]);

  // لا يوجد سجل في resource_availabilities

  $dto = new AnalyticsFilterDTO(now()->format('Y-m-d'), now()->format('Y-m-d'), 'daily', $projectId, 10);
  $results = (new EloquentBookingAnalyticsRepository())->getResourcePerformance($dto);

  expect($results['resources'][0]['occupancy_rate'])->toBe(0.0);
  expect($results['resources'][0]['total_available_hours'])->toBe(0.0);
});

test('getCancellationReport calculates summary and resource stats correctly', function () {
  $projectId = 1;
  $repo = new EloquentBookingAnalyticsRepository();

  // 1. إنشاء مورد
  $resource = Resource::factory()->create([
    'id' => 10,
    'project_id' => $projectId,
    'name' => 'Test Resource'
  ]);

  // 2. إنشاء حجز ملغى (مع استرداد)
  Booking::factory()->create([
    'project_id' => $projectId,
    'resource_id' => $resource->id,
    'status' => 'cancelled',
    'amount' => 100,
    'refund_amount' => 40,
    'created_at' => '2026-05-01 10:00:00'
  ]);

  // 3. إنشاء حجز No-Show
  Booking::factory()->create([
    'project_id' => $projectId,
    'resource_id' => $resource->id,
    'status' => 'no_show',
    'amount' => 80,
    'created_at' => '2026-05-01 11:00:00'
  ]);

  // 4. إنشاء حجز مكتمل (لحساب معدل الإلغاء الإجمالي)
  Booking::factory()->create([
    'project_id' => $projectId,
    'resource_id' => $resource->id,
    'status' => 'completed',
    'created_at' => '2026-05-01 12:00:00'
  ]);

  $dto = new AnalyticsFilterDTO('2026-05-01', '2026-05-05', 'daily', $projectId, 10);

  // Act
  $results = $repo->getCancellationReport($dto);

  // Assert Summary
  expect($results['summary']['total_cancellations'])->toBe(1);
  expect($results['summary']['total_amount_cancelled'])->toEqual(100.0);
  expect($results['summary']['total_refunded'])->toEqual(40.0);
  expect($results['summary']['cancellation_rate'])->toEqual(33.33); // 1 من أصل 3 حجوزات

  // Assert No-Show
  expect($results['no_show']['total'])->toBe(1);
  expect($results['no_show']['revenue_lost'])->toEqual(80.0);
  expect($results['no_show']['no_show_rate'])->toEqual(33.33);

  // Assert By Resource
  expect($results['by_resource'])->toHaveCount(1);
  expect($results['by_resource'][0]['resource_id'])->toBe(10);
  expect($results['by_resource'][0]['cancellations'])->toBe(1);

  // Assert Trend
  expect($results['trend'])->toHaveCount(1);
  expect($results['trend'][0]['label'])->toBe('2026-05-01');
  expect($results['trend'][0]['count'])->toBe(1);
});

test('getCancellationReport returns zero values when no cancellations exist', function () {
  $projectId = 1;
  $repo = new EloquentBookingAnalyticsRepository();

  // إنشاء حجز مكتمل فقط (لا توجد إلغاءات)
  Booking::factory()->create([
    'project_id' => $projectId,
    'status' => 'completed',
    'created_at' => now()
  ]);

  $dto = new AnalyticsFilterDTO(now()->subDay()->format('Y-m-d'), now()->format('Y-m-d'), 'daily', $projectId, 10);
  $results = $repo->getCancellationReport($dto);

  expect($results['summary']['total_cancellations'])->toBe(0);
  expect($results['summary']['cancellation_rate'])->toEqual(0.0);
  expect($results['no_show']['total'])->toBe(0);
  expect($results['by_resource'])->toBeEmpty();
  expect($results['trend'])->toBeEmpty();
});

test('getCancellationReport handles division by zero for rates', function () {
  $projectId = 999; // مشروع لا يملك أي بيانات
  $repo = new EloquentBookingAnalyticsRepository();

  $dto = new AnalyticsFilterDTO('2026-01-01', '2026-01-01', 'daily', $projectId, 10);
  $results = $repo->getCancellationReport($dto);

  // التأكد من أن الـ ternary operators في الكود تعمل ولا تسبب خطأ Division by zero
  expect($results['summary']['cancellation_rate'])->toEqual(0.0);
  expect($results['summary']['refund_rate'])->toEqual(0.0);
  expect($results['no_show']['no_show_rate'])->toEqual(0.0);
});

test('getPeakTimes calculates busiest periods and lead times correctly', function () {
  $projectId = 1;
  $repo = new EloquentBookingAnalyticsRepository();

  $resource = Resource::factory()->create(['project_id' => $projectId, 'name' => 'Fast Resource']);

  // حجز في يوم الجمعة، الساعة 14:00
  // تم إنشاؤه قبل 5 ساعات من بدايته (Lead Time = 5)
  Booking::factory()->create([
    'project_id' => $projectId,
    'resource_id' => $resource->id,
    'status' => 'confirmed',
    'amount' => 150,
    'start_at' => '2026-05-01 14:00:00', // الجمعة
    'created_at' => '2026-05-01 09:00:00'
  ]);

  // حجز آخر في نفس اليوم، الساعة 15:00
  // تم إنشاؤه قبل ساعة واحدة (Lead Time = 1)
  // متوسط الـ Lead Time للمورد يجب أن يكون (5+1)/2 = 3 ساعات
  Booking::factory()->create([
    'project_id' => $projectId,
    'resource_id' => $resource->id,
    'status' => 'completed',
    'amount' => 100,
    'start_at' => '2026-05-01 15:00:00',
    'created_at' => '2026-05-01 14:00:00'
  ]);

  $dto = new AnalyticsFilterDTO('2026-05-01', '2026-05-05', 'daily', $projectId, 10);

  // Act
  $results = $repo->getPeakTimes($dto);

  // 1. فحص الأيام (Day of Week)
  // الجمعة في PHP date('w') هو 5، وفي MySQL (5+1)-1 = 5
  $friday = collect($results['by_day_of_week'])->firstWhere('day_name', 'Friday');
  expect($friday['bookings_count'])->toBe(2);
  expect($friday['revenue'])->toEqual(250.0);

  // 2. فحص الساعات (By Hour)
  $hour14 = collect($results['by_hour'])->firstWhere('hour', 14);
  expect($hour14['bookings_count'])->toBe(1);
  expect($hour14['hour_label'])->toBe('14:00');

  // 3. فحص الأشهر (By Month)
  expect($results['by_month'][0]['month'])->toBe('2026-05');
  expect($results['by_month'][0]['bookings_count'])->toBe(2);

  // 4. فحص وقت الحجز المسبق (Lead Time)
  expect($results['avg_lead_time'])->toHaveCount(1);
  expect($results['avg_lead_time'][0]['name'])->toBe('Fast Resource');
  expect($results['avg_lead_time'][0]['avg_lead_time_hours'])->toEqual(3.0);
});

test('getPeakTimes returns empty collections when no data exists', function () {
  $projectId = 999;
  $repo = new EloquentBookingAnalyticsRepository();
  $dto = new AnalyticsFilterDTO('2026-01-01', '2026-01-01', 'daily', $projectId, 10);

  $results = $repo->getPeakTimes($dto);

  expect($results['by_day_of_week'])->toBeEmpty();
  expect($results['by_hour'])->toBeEmpty();
  expect($results['avg_lead_time'])->toBeEmpty();
});

test('resolveGroupBy returns correct SQL strings for different periods', function () {
  $repo = new EloquentBookingAnalyticsRepository();
  $column = 'created_at';

  // 1. حالة اليومي (Default)
  $daily = $repo->resolveGroupBy('daily', $column);
  expect($daily)->toBe("DATE(created_at)");

  // 2. حالة الشهري
  $monthly = $repo->resolveGroupBy('monthly', $column);
  expect($monthly)->toBe("DATE_FORMAT(created_at, '%Y-%m')");

  // 3. حالة الأسبوعي
  $weekly = $repo->resolveGroupBy('weekly', $column);
  expect($weekly)->toBe("DATE_FORMAT(created_at, '%x-W%v')");
});
