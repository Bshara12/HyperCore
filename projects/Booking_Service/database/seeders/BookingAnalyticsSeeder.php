<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookingAnalyticsSeeder extends Seeder
{
    // =========================================================
    // هذا الـ Seeder يشتغل على DB خاصة بـ Booking Service
    // الـ project_id و user_ids جايين من CMS Service
    // data_entry_id جاي من CMS entries (Services data type)
    // تأكد من تحديث config/seed_config.php أولاً
    // =========================================================

    public function run(): void
    {
        $this->command->info('🌱 [Booking] Starting Seeder...');

        // ─── قراءة الـ IDs من الـ Config ───────────────────────────
        $projectId = config('seed_config.project_id', 1);
        $userIds = config('seed_config.user_ids', range(1, 10));
        $entryIds = config('seed_config.entry_ids', range(1, 20));

        $this->command->info("📌 Using Project ID: {$projectId}");
        $this->command->info('📌 Using User IDs: '.implode(', ', $userIds));

        // ─── 1. Resources ──────────────────────────────────────────
        // كل resource مرتبط بـ data_entry من CMS (Services type)
        $resourceDefinitions = [
            ['name' => 'Conference Room A', 'type' => 'room',   'capacity' => 10, 'price' => 50],
            ['name' => 'Conference Room B', 'type' => 'room',   'capacity' => 5,  'price' => 30],
            ['name' => 'Tennis Court 1',    'type' => 'court',  'capacity' => 4,  'price' => 40],
            ['name' => 'Tennis Court 2',    'type' => 'court',  'capacity' => 4,  'price' => 40],
            ['name' => 'VIP Seat',          'type' => 'seat',   'capacity' => 1,  'price' => 100],
            ['name' => 'Regular Seat',      'type' => 'seat',   'capacity' => 1,  'price' => 20],
            ['name' => 'Dr. Ahmed Clinic',  'type' => 'doctor', 'capacity' => 1,  'price' => 80],
            ['name' => 'Dr. Sara Clinic',   'type' => 'doctor', 'capacity' => 1,  'price' => 80],
            ['name' => 'Yoga Studio',       'type' => 'room',   'capacity' => 15, 'price' => 0, 'free' => true],
            ['name' => 'Community Hall',    'type' => 'room',   'capacity' => 50, 'price' => 0, 'free' => true],
        ];

        $resourceIds = [];
        foreach ($resourceDefinitions as $index => $res) {
            $entryId = $entryIds[$index % count($entryIds)];
            $isFree = $res['free'] ?? false;

            $resourceId = DB::table('resources')->insertGetId([
                'data_entry_id' => $entryId,
                'project_id' => $projectId,
                'name' => $res['name'],
                'type' => $res['type'],
                'capacity' => $res['capacity'],
                'status' => 'active',
                'settings' => json_encode([]),
                'payment_type' => $isFree ? 'free' : 'paid',
                'price' => $isFree ? null : $res['price'],
                'created_at' => now()->subMonths(6),
                'updated_at' => now()->subMonths(6),
            ]);

            $resourceIds[] = [
                'id' => $resourceId,
                'capacity' => $res['capacity'],
                'price' => $isFree ? 0 : $res['price'],
                'is_free' => $isFree,
                'type' => $res['type'],
            ];
        }
        $this->command->info('✅ Resources created: '.count($resourceIds));

        // ─── 2. Resource Availabilities ────────────────────────────
        foreach ($resourceIds as $res) {
            $workDays = match ($res['type']) {
                'doctor' => [0, 1, 2, 3, 4],       // أحد - خميس
                'court' => [1, 2, 3, 4, 5, 6],    // اثنين - سبت
                default => [0, 1, 2, 3, 4, 5, 6], // كل الأيام
            };

            [$startTime, $endTime] = match ($res['type']) {
                'doctor' => ['09:00:00', '17:00:00'],
                'court' => ['06:00:00', '22:00:00'],
                'seat' => ['10:00:00', '23:00:00'],
                default => ['08:00:00', '20:00:00'],
            };

            foreach ($workDays as $day) {
                DB::table('resource_availabilities')->insert([
                    'resource_id' => $res['id'],
                    'day_of_week' => $day,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'slot_duration' => 60,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        $this->command->info('✅ Availabilities created');

        // ─── 3. Cancellation Policies ──────────────────────────────
        foreach ($resourceIds as $res) {
            if ($res['is_free']) {
                // مجاني — سياسة إلغاء بدون استرداد
                DB::table('booking_cancellation_policies')->insert([
                    'resource_id' => $res['id'],
                    'hours_before' => 1,
                    'refund_percentage' => 0,
                    'description' => 'Free resource - no refund applicable',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // مدفوع — 3 مستويات من سياسة الإلغاء
                DB::table('booking_cancellation_policies')->insert([
                    [
                        'resource_id' => $res['id'],
                        'hours_before' => 48,
                        'refund_percentage' => 100,
                        'description' => 'Full refund if cancelled 48h before',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'resource_id' => $res['id'],
                        'hours_before' => 24,
                        'refund_percentage' => 50,
                        'description' => '50% refund if cancelled 24h before',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'resource_id' => $res['id'],
                        'hours_before' => 2,
                        'refund_percentage' => 0,
                        'description' => 'No refund within 2h',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }
        $this->command->info('✅ Cancellation Policies created');

        // ─── 4. Bookings ───────────────────────────────────────────
        $statuses = [
            'pending',
            'confirmed',
            'confirmed',
            'completed',
            'completed',
            'completed',
            'completed',
            'cancelled',
            'cancelled',
            'no_show',
        ];

        $bookingCount = 0;

        foreach ($resourceIds as $res) {
            // كل resource يحصل على عدد حجوزات مختلف
            $count = rand(10, 25);

            for ($b = 0; $b < $count; $b++) {
                $userId = $userIds[array_rand($userIds)];
                $status = $statuses[array_rand($statuses)];
                $createdAt = now()->subDays(rand(1, 180));

                // وقت البداية
                $startAt = $createdAt->copy()
                    ->addDays(rand(1, 14))
                    ->setHour(rand(8, 18))
                    ->setMinute(0)
                    ->setSecond(0);

                $durationHours = rand(1, match ($res['type']) {
                    'doctor' => 1,
                    'court' => 2,
                    'seat' => 3,
                    default => 4,
                });

                $endAt = $startAt->copy()->addHours($durationHours);
                $amount = $res['is_free'] ? 0 : ($res['price'] * $durationHours);

                // حساب الاسترداد
                $refundAmount = null;
                if ($status === 'cancelled' && $amount > 0) {
                    $hoursBeforeStart = $createdAt->diffInHours($startAt);
                    $refundAmount = match (true) {
                        $hoursBeforeStart >= 48 => $amount,
                        $hoursBeforeStart >= 24 => $amount * 0.5,
                        default => 0,
                    };
                }

                DB::table('bookings')->insert([
                    'resource_id' => $res['id'],
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'payment_id' => ($status !== 'pending' && $amount > 0)
                      ? rand(1, 999)
                      : null,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'status' => $status,
                    'amount' => $amount,
                    'currency' => 'USD',
                    'notes' => rand(0, 1) ? 'Please prepare the room' : null,
                    'cancellation_reason' => $status === 'cancelled'
                      ? 'Schedule conflict'
                      : null,
                    'refund_amount' => $refundAmount,
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ]);

                $bookingCount++;
            }
        }
        $this->command->info("✅ Bookings created: {$bookingCount}");

        // ─── طباعة النتائج ──────────────────────────────────────────
        $this->command->info('🎉 [Booking] Seeder completed!');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Resources',             count($resourceIds)],
                ['Availabilities',        count($resourceIds).' × days'],
                ['Cancellation Policies', count($resourceIds).' × policies'],
                ['Bookings',              $bookingCount],
            ]
        );
    }
}
