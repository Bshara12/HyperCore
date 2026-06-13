<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Pulse360BookingSeeder
 *
 * Belongs to: Booking Service
 *
 * Covers:
 *  - resources
 *  - resource_availabilities
 *  - booking_cancellation_policies
 *  - bookings
 *
 * ⚠️  IMPORTANT:
 *  Run AFTER Pulse360CmsSeeder (on CMS service) has finished.
 *  This seeder reads event data_entry_ids from the shared DB
 *  (or from a config/env value if services use separate DBs).
 *
 * Run: php artisan db:seed --class=Pulse360BookingSeeder
 */
class Pulse360BookingSeeder extends Seeder
{
    // ─── Config ───────────────────────────────────────────────────────────────

    /**
     * If Booking service shares the same DB connection as CMS,
     * set this to null and the seeder will auto-detect the project.
     * If separate DBs, set the project_id manually here.
     */
    private ?int $projectId = null;

    /**
     * The data_type slug used for events in CMS service.
     * Must match what Pulse360CmsSeeder created.
     */
    private string $eventTypeSlug = 'event';

    // ─── Internal state ───────────────────────────────────────────────────────
    private array $eventEntries  = [];
    private array $userIds       = [];
    private array $resourceIds   = [];

    // =========================================================================
    // ENTRY POINT
    // =========================================================================

    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->resolveProjectId();
            $this->loadEventEntries();
            $this->loadUserIds();
            $this->seedResources();
            $this->seedBookings();

            DB::commit();
            $this->printSummary();

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // 1. RESOLVE PROJECT ID
    // =========================================================================

    private function resolveProjectId(): void
    {
        if ($this->projectId !== null) return;

        $project = DB::table('projects')->where('slug', 'pulse360')->first();

        if (! $project) {
            throw new \RuntimeException(
                'Project "pulse360" not found. Run Pulse360CmsSeeder first.'
            );
        }

        $this->projectId = $project->id;
    }

    // =========================================================================
    // 2. LOAD EVENT ENTRIES FROM CMS TABLES
    // =========================================================================

    private function loadEventEntries(): void
    {
        $eventTypeId = DB::table('data_types')
            ->where('project_id', $this->projectId)
            ->where('slug', $this->eventTypeSlug)
            ->value('id');

        if (! $eventTypeId) {
            throw new \RuntimeException(
                'Event data type not found. Run Pulse360CmsSeeder first.'
            );
        }

        // Load event entries with their titles (from data_entry_values)
        $titleFieldId = DB::table('data_type_fields')
            ->where('data_type_id', $eventTypeId)
            ->where('name', 'title')
            ->value('id');

        $dateFieldId = DB::table('data_type_fields')
            ->where('data_type_id', $eventTypeId)
            ->where('name', 'date')
            ->value('id');

        $accessFieldId = DB::table('data_type_fields')
            ->where('data_type_id', $eventTypeId)
            ->where('name', 'access')
            ->value('id');

        $entries = DB::table('data_entries')
            ->where('project_id', $this->projectId)
            ->where('data_type_id', $eventTypeId)
            ->where('status', 'published')
            ->get();

        foreach ($entries as $entry) {
            $title = $titleFieldId
                ? DB::table('data_entry_values')
                    ->where('data_entry_id', $entry->id)
                    ->where('data_type_field_id', $titleFieldId)
                    ->value('value')
                : $entry->slug;

            $date = $dateFieldId
                ? DB::table('data_entry_values')
                    ->where('data_entry_id', $entry->id)
                    ->where('data_type_field_id', $dateFieldId)
                    ->value('value')
                : null;

            $access = $accessFieldId
                ? DB::table('data_entry_values')
                    ->where('data_entry_id', $entry->id)
                    ->where('data_type_field_id', $accessFieldId)
                    ->value('value')
                : 'paid';

            $this->eventEntries[] = [
                'id'     => $entry->id,
                'slug'   => $entry->slug,
                'title'  => $title ?? $entry->slug,
                'date'   => $date,
                'access' => $access ?? 'paid',
            ];
        }

        if (empty($this->eventEntries)) {
            throw new \RuntimeException(
                'No published events found in CMS. Run Pulse360CmsSeeder first.'
            );
        }
    }

    // =========================================================================
    // 3. LOAD USER IDS
    // =========================================================================

    private function loadUserIds(): void
    {
        $this->userIds = DB::table('users')
            ->whereNotNull('email_verified_at')
            ->pluck('id')
            ->toArray();

        if (empty($this->userIds)) {
            throw new \RuntimeException(
                'No verified users found. Run Pulse360CmsSeeder first.'
            );
        }
    }

    // =========================================================================
    // 4. RESOURCES + AVAILABILITIES + CANCELLATION POLICIES
    // =========================================================================

    private function seedResources(): void
    {
        // Map event titles → pricing config
        $pricingMap = $this->pricingConfig();

        foreach ($this->eventEntries as $event) {
            // Skip if resource already exists for this event entry
            $existing = DB::table('resources')
                ->where('data_entry_id', $event['id'])
                ->where('project_id', $this->projectId)
                ->first();

            if ($existing) {
                $this->resourceIds[] = [
                    'id'    => $existing->id,
                    'event' => $event,
                ];
                continue;
            }

            // Determine pricing from map or fallback to access field
            $config = $this->resolvePricingConfig($event, $pricingMap);

            $resourceId = DB::table('resources')->insertGetId([
                'data_entry_id' => $event['id'],
                'project_id'    => $this->projectId,
                'name'          => $event['title'],
                'type'          => 'event',
                'capacity'      => $config['capacity'],
                'status'        => 'active',
                'payment_type'  => $config['price'] > 0 ? 'paid' : 'free',
                'price'         => $config['price'],
                'settings'      => json_encode([
                    'slot_duration_minutes' => $config['slot_duration'],
                    'booking_window_days'   => 90,
                    'max_bookings_per_user' => 1,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->resourceIds[] = [
                'id'    => $resourceId,
                'event' => $event,
            ];

            $this->seedAvailabilities($resourceId, $config['slot_duration']);
            $this->seedCancellationPolicies($resourceId, $config['price']);
        }
    }

    private function seedAvailabilities(int $resourceId, int $slotDuration): void
    {
        // Monday–Friday: 09:00–18:00
        // Saturday: 10:00–16:00 (half day)
        $schedule = [
            ['day' => 1, 'start' => '09:00:00', 'end' => '18:00:00'], // Monday
            ['day' => 2, 'start' => '09:00:00', 'end' => '18:00:00'], // Tuesday
            ['day' => 3, 'start' => '09:00:00', 'end' => '18:00:00'], // Wednesday
            ['day' => 4, 'start' => '09:00:00', 'end' => '18:00:00'], // Thursday
            ['day' => 5, 'start' => '09:00:00', 'end' => '18:00:00'], // Friday
            ['day' => 6, 'start' => '10:00:00', 'end' => '16:00:00'], // Saturday
        ];

        foreach ($schedule as $slot) {
            $exists = DB::table('resource_availabilities')
                ->where('resource_id', $resourceId)
                ->where('day_of_week', $slot['day'])
                ->exists();

            if ($exists) continue;

            DB::table('resource_availabilities')->insert([
                'resource_id'   => $resourceId,
                'day_of_week'   => $slot['day'],
                'start_time'    => $slot['start'],
                'end_time'      => $slot['end'],
                'slot_duration' => $slotDuration,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    private function seedCancellationPolicies(int $resourceId, float $price): void
    {
        $existingCount = DB::table('booking_cancellation_policies')
            ->where('resource_id', $resourceId)
            ->count();

        if ($existingCount > 0) return;

        $policies = $price > 0
            ? [
                // Paid events: tiered refund policy
                [
                    'hours_before'      => 72,
                    'refund_percentage' => 100,
                    'description'       => 'Full refund if cancelled 72+ hours before the event.',
                ],
                [
                    'hours_before'      => 48,
                    'refund_percentage' => 75,
                    'description'       => '75% refund if cancelled 48–72 hours before the event.',
                ],
                [
                    'hours_before'      => 24,
                    'refund_percentage' => 50,
                    'description'       => '50% refund if cancelled 24–48 hours before the event.',
                ],
                [
                    'hours_before'      => 0,
                    'refund_percentage' => 0,
                    'description'       => 'No refund within 24 hours of the event.',
                ],
            ]
            : [
                // Free events: simple 24h cancellation
                [
                    'hours_before'      => 24,
                    'refund_percentage' => 100,
                    'description'       => 'Cancel at least 24 hours before to free your spot.',
                ],
                [
                    'hours_before'      => 0,
                    'refund_percentage' => 0,
                    'description'       => 'No-show policy: spot is forfeited within 24 hours.',
                ],
            ];

        foreach ($policies as $policy) {
            DB::table('booking_cancellation_policies')->insert(array_merge($policy, [
                'resource_id' => $resourceId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]));
        }
    }

    // =========================================================================
    // 5. BOOKINGS
    // =========================================================================

    private function seedBookings(): void
    {
        foreach ($this->resourceIds as $resourceData) {
            $resourceId = $resourceData['id'];
            $event      = $resourceData['event'];

            $resource = DB::table('resources')->where('id', $resourceId)->first();
            if (! $resource) continue;

            // Determine booking date base
            $eventDate = $event['date']
                ? Carbon::parse($event['date'])
                : Carbon::now()->addDays(rand(10, 90));

            // Generate bookings: 3–8 per resource
            $bookingCount  = rand(3, 8);
            $usedUserIds   = [];
            $availableUsers = $this->userIds;
            shuffle($availableUsers);

            $statuses = ['confirmed', 'confirmed', 'confirmed', 'pending', 'cancelled', 'completed', 'confirmed'];

            for ($i = 0; $i < $bookingCount && $i < count($availableUsers); $i++) {
                $userId = $availableUsers[$i];

                // Skip if this user already booked this resource
                $alreadyBooked = DB::table('bookings')
                    ->where('resource_id', $resourceId)
                    ->where('user_id', $userId)
                    ->exists();

                if ($alreadyBooked) continue;

                $status   = $statuses[$i % count($statuses)];
                $slotHour = 9 + ($i * 2); // 09:00, 11:00, 13:00...
                if ($slotHour >= 18) $slotHour = 9;

                $startAt = (clone $eventDate)->setTime($slotHour, 0, 0);
                $endAt   = (clone $startAt)->addHours(1);

                // For past events, use completed status
                if ($eventDate->isPast() && $status === 'confirmed') {
                    $status = 'completed';
                }

                $amount        = $resource->price ?? 0;
                $refundAmount  = null;

                if ($status === 'cancelled' && $amount > 0) {
                    // Apply 50% refund (simulating 24-48h cancellation window)
                    $refundAmount = round($amount * 0.5, 2);
                }

                $createdAt = now()->subDays(rand(1, 45));

                DB::table('bookings')->insert([
                    'resource_id'         => $resourceId,
                    'user_id'             => $userId,
                    'project_id'          => $this->projectId,
                    'payment_id'          => null,
                    'start_at'            => $startAt,
                    'end_at'              => $endAt,
                    'status'              => $status,
                    'amount'              => $amount,
                    'currency'            => 'USD',
                    'notes'               => $this->randomNote($status),
                    'cancellation_reason' => $status === 'cancelled'
                        ? $this->randomCancellationReason()
                        : null,
                    'refund_amount' => $refundAmount,
                    'created_at'    => $createdAt,
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Pricing configuration for known events.
     * Keys are partial title matches (case-insensitive).
     */
    private function pricingConfig(): array
    {
        return [
            'AI Future Summit'               => ['price' => 299, 'capacity' => 500,  'slot_duration' => 60],
            'Global Tech Innovators'         => ['price' => 199, 'capacity' => 300,  'slot_duration' => 60],
            'Startup Pitch Night'            => ['price' => 0,   'capacity' => 80,   'slot_duration' => 30],
            'Space Exploration Expo'         => ['price' => 149, 'capacity' => 2000, 'slot_duration' => 90],
            'Cybersecurity World Summit'     => ['price' => 249, 'capacity' => 400,  'slot_duration' => 60],
            'Future of Robotics'             => ['price' => 179, 'capacity' => 200,  'slot_duration' => 60],
            'Climate Innovation Forum'       => ['price' => 0,   'capacity' => 150,  'slot_duration' => 60],
            'HealthTech Summit'              => ['price' => 199, 'capacity' => 350,  'slot_duration' => 60],
            'Global Finance Leaders'         => ['price' => 499, 'capacity' => 100,  'slot_duration' => 120],
            'Quantum Computing Business'     => ['price' => 349, 'capacity' => 200,  'slot_duration' => 90],
            'Media & Journalism'             => ['price' => 0,   'capacity' => 200,  'slot_duration' => 60],
            'Biotech Investment Congress'     => ['price' => 399, 'capacity' => 300,  'slot_duration' => 60],
            'Web3 & Decentralized Finance'   => ['price' => 149, 'capacity' => 250,  'slot_duration' => 60],
            'Women in Tech Leadership'       => ['price' => 0,   'capacity' => 500,  'slot_duration' => 60],
            'Energy Transition Summit'       => ['price' => 249, 'capacity' => 400,  'slot_duration' => 60],
            'E-Sports World Championship'    => ['price' => 99,  'capacity' => 5000, 'slot_duration' => 120],
            'Global Mental Health'           => ['price' => 0,   'capacity' => 300,  'slot_duration' => 60],
            'Autonomous Vehicles Conference' => ['price' => 179, 'capacity' => 250,  'slot_duration' => 60],
            'Digital Transformation Awards'  => ['price' => 199, 'capacity' => 200,  'slot_duration' => 120],
            'Genomics & Precision Medicine'  => ['price' => 299, 'capacity' => 300,  'slot_duration' => 60],
        ];
    }

    private function resolvePricingConfig(array $event, array $pricingMap): array
    {
        $title = $event['title'];

        foreach ($pricingMap as $keyword => $config) {
            if (stripos($title, $keyword) !== false) {
                return $config;
            }
        }

        // Fallback: derive from access field
        $isPaid = ($event['access'] === 'paid');

        return [
            'price'         => $isPaid ? rand(79, 249) : 0,
            'capacity'      => rand(50, 300),
            'slot_duration' => [30, 60, 90][array_rand([30, 60, 90])],
        ];
    }

    private function randomNote(string $status): ?string
    {
        if ($status === 'confirmed' || $status === 'completed') {
            $notes = [
                'Vegetarian meal preference.',
                'Requires wheelchair-accessible seating.',
                'Will attend Day 1 only.',
                'Joining virtually for Day 2.',
                null,
                null,
                null,
            ];
            return $notes[array_rand($notes)];
        }
        return null;
    }

    private function randomCancellationReason(): string
    {
        $reasons = [
            'Schedule conflict — cannot attend.',
            'Travel plans changed.',
            'Company policy freeze on events spending.',
            'Found an overlapping conference.',
            'Personal emergency.',
            'Team restructuring — attendee no longer with company.',
        ];
        return $reasons[array_rand($reasons)];
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    private function printSummary(): void
    {
        $resourceCount = count($this->resourceIds);
        $bookingCount  = DB::table('bookings')
            ->where('project_id', $this->projectId)
            ->count();

        echo "\n";
        echo "╔══════════════════════════════════════════════╗\n";
        echo "║      PULSE360 BOOKING SEEDER COMPLETE        ║\n";
        echo "╠══════════════════════════════════════════════╣\n";
        echo "║ Service:        Booking\n";
        echo "║ Project ID:     {$this->projectId}\n";
        echo "║ Events loaded:  " . count($this->eventEntries) . "\n";
        echo "║ Resources:      {$resourceCount}\n";
        echo "║ Total bookings: {$bookingCount}\n";
        echo "╠══════════════════════════════════════════════╣\n";
        echo "║ STATUS BREAKDOWN:\n";

        $statuses = ['confirmed', 'pending', 'cancelled', 'completed'];
        foreach ($statuses as $s) {
            $count = DB::table('bookings')
                ->where('project_id', $this->projectId)
                ->where('status', $s)
                ->count();
            printf("║  %-12s %d\n", ucfirst($s) . ':', $count);
        }

        echo "╚══════════════════════════════════════════════╝\n\n";
    }
}