<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Booking data for the comprehensive project (Nova Marketplace).
 *
 * Each resource mirrors a CMS "Service" entry: resources.data_entry_id holds
 * the CMS entry id, which only lines up because both seeders use the ids fixed
 * in DemoIds.
 *
 * Bookings are spread over the last ten weeks across every status — including
 * cancellations with partial refunds and a no-show — so the overview, trend,
 * resource performance, cancellation and peak-time reports all have real
 * shapes to describe.
 *
 * Run: php artisan db:seed --class="Database\Seeders\Demo\DemoBookingSeeder"
 *
 * Safe to re-run — the project's rows are cleared first.
 */
class DemoBookingSeeder extends Seeder
{
    private const CURRENCY = 'USD';

    /** CMS service entry id => [resource id, name, type, capacity, price] */
    private const RESOURCES = [
        9431 => [9601, 'Recording Studio Session', 'studio', 6, 120.00],
        9432 => [9602, 'Workspace Consultation', 'consultation', 1, 80.00],
        9433 => [9603, 'Outdoor Gear Fitting', 'fitting', 2, 35.00],
    ];

    public function run(): void
    {
        $projectId = DemoIds::OWNER_PROJECT_MARKETPLACE;

        DB::transaction(function () use ($projectId) {
            $this->purge($projectId);
            $this->seedResources($projectId);
            $this->seedAvailabilities();
            $this->seedPolicies();
            $this->seedBookings($projectId);
        });

        // Written with DB::table(), so the analytics caches for this project
        // still hold the pre-seed picture.
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            $this->command?->warn('Could not flush the cache: '.$e->getMessage());
        }

        $this->command?->info("Booking demo data seeded for project {$projectId} (Nova Marketplace):");
        $this->command?->table(
            ['table', 'rows'],
            [
                ['resources', DB::table('resources')->where('project_id', $projectId)->count()],
                ['availabilities', DB::table('resource_availabilities')->whereIn('resource_id', array_column(self::RESOURCES, 0))->count()],
                ['cancellation policies', DB::table('booking_cancellation_policies')->whereIn('resource_id', array_column(self::RESOURCES, 0))->count()],
                ['bookings', DB::table('bookings')->where('project_id', $projectId)->count()],
            ]
        );
    }

    // ─────────────────────────────────────────────────────────
    private function purge(int $projectId): void
    {
        $resourceIds = array_column(self::RESOURCES, 0);

        // Bookings first: they carry a foreign key to resources.
        DB::table('bookings')->where('project_id', $projectId)->delete();
        DB::table('resource_availabilities')->whereIn('resource_id', $resourceIds)->delete();
        DB::table('booking_cancellation_policies')->whereIn('resource_id', $resourceIds)->delete();
        DB::table('resources')->where('project_id', $projectId)->delete();
    }

    // ─────────────────────────────────────────────────────────
    private function seedResources(int $projectId): void
    {
        foreach (self::RESOURCES as $entryId => [$resourceId, $name, $type, $capacity, $price]) {
            DB::table('resources')->insert([
                'id' => $resourceId,
                'data_entry_id' => $entryId,
                'project_id' => $projectId,
                'name' => $name,
                'type' => $type,
                'capacity' => $capacity,
                'status' => 'active',
                'settings' => json_encode(['buffer_minutes' => 15]),
                'payment_type' => 'paid',
                'price' => $price,
                'created_at' => now()->subDays(70),
                'updated_at' => now()->subDays(70),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────
    private function seedAvailabilities(): void
    {
        // Sunday..Thursday, the working week these demo projects assume.
        $workingDays = [0, 1, 2, 3, 4];

        $windows = [
            9601 => ['10:00:00', '20:00:00', 60],
            9602 => ['09:00:00', '17:00:00', 45],
            9603 => ['11:00:00', '19:00:00', 30],
        ];

        foreach ($windows as $resourceId => [$start, $end, $slot]) {
            foreach ($workingDays as $day) {
                DB::table('resource_availabilities')->insert([
                    'resource_id' => $resourceId,
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'slot_duration' => $slot,
                    'is_active' => true,
                    'created_at' => now()->subDays(70),
                    'updated_at' => now()->subDays(70),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    private function seedPolicies(): void
    {
        // A tiered refund ladder per resource: the closer to the start, the
        // less you get back.
        $tiers = [
            [72, 100, 'Full refund more than 72 hours ahead.'],
            [24, 50, 'Half refund between 24 and 72 hours ahead.'],
            [2, 0, 'No refund within 2 hours of the booking.'],
        ];

        foreach (array_column(self::RESOURCES, 0) as $resourceId) {
            foreach ($tiers as [$hours, $percentage, $description]) {
                DB::table('booking_cancellation_policies')->insert([
                    'resource_id' => $resourceId,
                    'hours_before' => $hours,
                    'refund_percentage' => $percentage,
                    'description' => $description,
                    'created_at' => now()->subDays(70),
                    'updated_at' => now()->subDays(70),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    private function seedBookings(int $projectId): void
    {
        /*
         | [days ago, resource, customer, status, start hour, refund]
         |
         | Hours are deliberately clustered around 11:00 and 17:00 so the peak
         | times report has an actual peak instead of a flat line.
         */
        $bookings = [
            [66, 9601, DemoIds::CUSTOMER_ONE_ID,   'completed', 11, null],
            [59, 9602, DemoIds::CUSTOMER_TWO_ID,   'completed', 11, null],
            [52, 9601, DemoIds::CUSTOMER_THREE_ID, 'completed', 17, null],
            [45, 9603, DemoIds::CUSTOMER_ONE_ID,   'cancelled', 12, 35.00],
            [38, 9601, DemoIds::CUSTOMER_TWO_ID,   'completed', 17, null],
            [31, 9602, DemoIds::CUSTOMER_THREE_ID, 'no_show',   11, null],
            [24, 9603, DemoIds::CUSTOMER_ONE_ID,   'completed', 11, null],
            [17, 9601, DemoIds::CUSTOMER_TWO_ID,   'cancelled', 17, 60.00],
            [10, 9602, DemoIds::CUSTOMER_ONE_ID,   'completed', 17, null],
            [4,  9601, DemoIds::CUSTOMER_THREE_ID, 'confirmed', 11, null],
            [1,  9603, DemoIds::CUSTOMER_TWO_ID,   'confirmed', 17, null],
            [-3, 9601, DemoIds::CUSTOMER_ONE_ID,   'pending',   11, null],
            [-6, 9602, DemoIds::CUSTOMER_THREE_ID, 'pending',   11, null],
        ];

        $prices = [];

        foreach (self::RESOURCES as [$resourceId, , , , $price]) {
            $prices[$resourceId] = $price;
        }

        foreach ($bookings as [$daysAgo, $resourceId, $userId, $status, $hour, $refund]) {
            // Negative "days ago" means a future booking — the pending ones.
            $startAt = now()->subDays($daysAgo)->setTime($hour, 0);
            $endAt = (clone $startAt)->addMinutes(60);

            // Created a few days before the slot, which is what the lead-time
            // part of the peak-times report measures.
            $createdAt = (clone $startAt)->subDays(4);

            DB::table('bookings')->insert([
                'resource_id' => $resourceId,
                'user_id' => $userId,
                'project_id' => $projectId,
                'payment_id' => null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => $status,
                'amount' => $prices[$resourceId],
                'currency' => self::CURRENCY,
                'notes' => $status === 'no_show' ? 'Customer did not arrive.' : null,
                'cancellation_reason' => $status === 'cancelled' ? 'Schedule conflict.' : null,
                'refund_amount' => $refund,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
