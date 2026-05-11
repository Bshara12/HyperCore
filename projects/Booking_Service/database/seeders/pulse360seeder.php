<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class pulse360seeder extends Seeder
{
    public function run()
    {
        DB::beginTransaction();

        try {

            $projectId = 1;

            // 🟢 events (لازم تكون موجودة من CMS)
            $events = [
                ['id' => 1, 'name' => 'AI Conference 2026', 'price' => 100, 'capacity' => 50],
                ['id' => 2, 'name' => 'Tech Meetup', 'price' => 0, 'capacity' => 30],
                ['id' => 3, 'name' => 'Startup Pitch Night', 'price' => 25, 'capacity' => 20],
            ];

            foreach ($events as $event) {

                // 🟢 1. resource
                $resourceId = DB::table('resources')->insertGetId([
                    'data_entry_id' => $event['id'], // 🔥 مهم: جاي من CMS
                    'project_id' => $projectId,
                    'name' => $event['name'],
                    'type' => 'event',
                    'capacity' => $event['capacity'],
                    'status' => 'active',
                    'payment_type' => $event['price'] > 0 ? 'paid' : 'free',
                    'price' => $event['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 🟢 2. availability
                for ($day = 0; $day <= 4; $day++) {

                    DB::table('resource_availabilities')->insert([
                        'resource_id' => $resourceId,
                        'day_of_week' => $day,
                        'start_time' => '10:00:00',
                        'end_time' => '18:00:00',
                        'slot_duration' => 60,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 🟢 3. cancellation
                DB::table('booking_cancellation_policies')->insert([
                    'resource_id' => $resourceId,
                    'hours_before' => 24,
                    'refund_percentage' => 80,
                    'description' => 'Cancel before 24h',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            echo "\n✅ Booking Events Seeded\n";

            $resources = DB::table('resources')->get();

            foreach ($resources as $r) {
                echo "Event: {$r->name} | Capacity: {$r->capacity}\n";
            }

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
